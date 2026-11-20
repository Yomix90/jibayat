<?php

namespace App\Domain\Documents\Services;

use App\Domain\Documents\Models\StampBook;
use App\Domain\Documents\Models\StampPack;
use App\Domain\Documents\Models\StampRemittance;
use App\Models\User;
use App\Support\PyMath;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Régie des timbres et tickets — portage de Flask modules/regie.py :
 *   ouvrir_paquet ➜ openPack()   versement_employe ➜ employeePayment()   creer_bordereau ➜ createRemittance()   index ➜ stats()
 * Vérifié par tests/Feature/Documents/StampParityTest.php contre les routes Python.
 */
class StampService
{
    public const BOOKS_PER_PACK = 10;

    /** Nombre de vignettes d'un paquet : dernier − premier + 1 (Flask ajouter_paquet). */
    public static function quantity($first, $last): int
    {
        return is_numeric($first) && is_numeric($last) ? (int) $last - (int) $first + 1 : 0;
    }

    /**
     * Ouverture d'un paquet : 10 carnets de numéros consécutifs « {paquet}-C01… », le dernier allant jusqu'au dernier numéro.
     * Écart : un paquet de moins de 10 vignettes donnait chez Flask des carnets invalides (début > fin) ; on crée alors
     * un carnet par vignette.
     *
     * @return int nombre de carnets créés
     */
    public function openPack(StampPack $pack): int
    {
        return DB::transaction(function () use ($pack) {
            $pack = StampPack::whereKey($pack->id)->lockForUpdate()->firstOrFail();
            if (StampBook::where('stamp_pack_id', $pack->id)->exists()) {
                throw ValidationException::withMessages(['pack' => 'Ce paquet a déjà été ouvert.']);
            }
            if (! is_numeric($pack->first_number) || ! is_numeric($pack->last_number) || (int) $pack->last_number < (int) $pack->first_number) {
                throw ValidationException::withMessages(['pack' => 'Numéros de paquet invalides.']);
            }
            $first = (int) $pack->first_number;
            $last = (int) $pack->last_number;
            $total = $last - $first + 1;
            $count = min(self::BOOKS_PER_PACK, $total);
            $step = max(1, intdiv($total, self::BOOKS_PER_PACK));
            for ($i = 0; $i < $count; $i++) {
                $start = $first + $i * $step;
                $end = $i < $count - 1 ? $first + ($i + 1) * $step - 1 : $last;
                StampBook::create(['stamp_pack_id' => $pack->id, 'stamp_value_id' => $pack->stamp_value_id,
                    'number' => sprintf('%s-C%02d', $pack->number, $i + 1), 'first_number' => $start, 'last_number' => $end, 'status' => 'en_stock']);
            }
            $pack->update(['status' => 'ouvert']);

            return $count;
        });
    }

    public function assign(StampBook $book, int $serviceId, int $employeeId): void
    {
        if ($book->status !== 'en_stock') {
            throw ValidationException::withMessages(['book' => 'Seul un carnet en stock peut être affecté.']);
        }
        $book->update(['status' => 'affecte', 'service_id' => $serviceId, 'employee_id' => $employeeId, 'assigned_on' => CarbonImmutable::today()->toDateString()]);
    }

    /**
     * Versement d'un employé pour un carnet : carnet « consommé », écart = versé − valeur théorique (valeur × unités).
     *
     * @return float écart
     */
    public function employeePayment(StampBook $book, float $amount, string $paidOn, ?string $observation, User $by): float
    {
        if ($book->status !== 'affecte') {
            throw ValidationException::withMessages(['book' => 'Seul un carnet affecté peut être versé.']);
        }
        $value = $book->stampValue;
        $theoretical = (float) $value->unit_value * (int) $value->units_per_book;
        $difference = PyMath::round($amount - $theoretical, 2);
        $book->update(['status' => 'consomme', 'amount_paid' => $amount, 'difference' => $difference, 'employee_paid_on' => $paidOn, 'observation' => $observation]);
        activity('pieces')->causedBy($by)->performedOn($book)->withProperties(['montant' => $amount, 'ecart' => $difference])
            ->log("Versement employé — carnet {$book->number}");

        return $difference;
    }

    /** Bordereau de versement au percepteur : n° saisi (unique) ou « BRD{année}{n°} » ; total = somme des montants versés. */
    public function createRemittance(array $bookIds, string $remittedOn, ?string $number, User $by): StampRemittance
    {
        if (! $bookIds) {
            throw ValidationException::withMessages(['books' => 'Aucun carnet sélectionné.']);
        }

        return DB::transaction(function () use ($bookIds, $remittedOn, $number, $by) {
            $number = trim((string) $number);
            if ($number === '') {
                $n = StampRemittance::count() + 1;
                do {
                    $number = sprintf('BRD%d%05d', CarbonImmutable::today()->year, $n++);
                } while (StampRemittance::where('number', $number)->exists());
            } elseif (StampRemittance::where('number', $number)->exists()) {
                throw ValidationException::withMessages(['number' => "Le numéro de bordereau « {$number} » existe déjà."]);
            }
            $books = StampBook::whereIn('id', $bookIds)->where('status', 'consomme')->whereNull('stamp_remittance_id')->lockForUpdate()->get();
            if ($books->isEmpty()) {
                throw ValidationException::withMessages(['books' => 'Aucun carnet versé et non encore bordereauté.']);
            }
            $r = StampRemittance::create(['number' => $number, 'remitted_on' => $remittedOn, 'agent_id' => $by->id,
                'total_amount' => PyMath::round((float) $books->sum('amount_paid'), 2)]);
            StampBook::whereIn('id', $books->pluck('id'))->update(['stamp_remittance_id' => $r->id, 'remitted_on' => $remittedOn]);
            activity('pieces')->causedBy($by)->performedOn($r)->log("Bordereau de versement {$number} — {$books->count()} carnet(s)");

            return $r;
        });
    }

    /** Indicateurs du tableau de bord (Flask regie.index). */
    public function stats(?int $year = null): array
    {
        $year ??= CarbonImmutable::today()->year;
        $monthly = array_fill(1, 12, 0.0);
        foreach (StampBook::where('status', 'consomme')->whereYear('employee_paid_on', $year)
            ->selectRaw('MONTH(employee_paid_on) m, SUM(amount_paid) s')->groupBy('m')->get() as $row) {
            $monthly[(int) $row->m] = (float) $row->s;
        }

        return [
            'total_paquets' => StampPack::count(), 'total_carnets' => StampBook::count(),
            'en_stock' => StampBook::where('status', 'en_stock')->count(), 'affectes' => StampBook::where('status', 'affecte')->count(),
            'consommes' => StampBook::where('status', 'consomme')->count(),
            'total_verse' => (float) StampBook::where('status', 'consomme')->sum('amount_paid'),
            'monthly' => array_values($monthly),
        ];
    }
}
