<?php

namespace App\Domain\Regie\Services;

use App\Domain\Caisse\Services\NumberGenerator;
use App\Domain\Regie\Models\CashReport;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Procès-verbaux de la régie :
 *   - arrêté de caisse (chaque mois) ;
 *   - vérification de caisse (chaque trimestre, par le comptable ou un vérificateur) ;
 *   - récolement des tickets et vignettes (dernier jour du trimestre) ;
 *   - remise de service (passation entre régisseurs).
 * Le solde théorique vient du livre de caisse ; le solde constaté du billetage. Le PV est figé ; il s'annule avec un motif.
 */
class MinutesService
{
    public const TYPES = [
        'arrete' => ['fr' => 'PV d’arrêté de caisse', 'ar' => 'محضر حصر الصندوق', 'prefix' => 'PVA', 'periodicity' => 'month', 'cash' => true, 'stock' => false],
        'verification' => ['fr' => 'PV de vérification de caisse', 'ar' => 'محضر مراقبة الصندوق', 'prefix' => 'PVV', 'periodicity' => 'quarter', 'cash' => true, 'stock' => false],
        'recolement' => ['fr' => 'PV de récolement des tickets et vignettes', 'ar' => 'محضر جرد التذاكر والطوابع', 'prefix' => 'PVR', 'periodicity' => 'quarter', 'cash' => false, 'stock' => true],
        'remise' => ['fr' => 'PV de remise de service', 'ar' => 'محضر تسليم السلط', 'prefix' => 'PVS', 'periodicity' => null, 'cash' => true, 'stock' => true],
    ];

    /** Billetage (dirhams). */
    public const DENOMINATIONS = ['200', '100', '50', '20', '10', '5', '2', '1', '0.5', '0.2', '0.1'];

    public function __construct(protected NumberGenerator $numbers, protected Ledger $ledger, protected StockService $stock) {}

    /** Période d'un PV à une date : mois (arrêté) ou trimestre (vérification, récolement). */
    public static function period(string $type, string $date): int
    {
        $d = CarbonImmutable::parse($date);

        return match (self::TYPES[$type]['periodicity']) {
            'month' => $d->month, 'quarter' => $d->quarter, default => 0,
        };
    }

    /** Éléments théoriques à la date : solde du livre de caisse, stock de la régie, opérations en suspens. */
    public function theoretical(string $type, string $date): array
    {
        $t = self::TYPES[$type];
        $out = ['balance' => $t['cash'] ? $this->ledger->balanceAt($date) : 0.0];
        if ($t['stock']) {
            $out['stock'] = collect($this->stock->state())->map(fn ($v) => ['id' => $v['id'], 'label' => $v['label'], 'unit_value' => $v['unit_value'],
                'theoretical' => $v['regie'], 'with_agents' => $v['with_agents']])->values()->all();
        }
        if ($type === 'remise') {
            $out['pending'] = [
                'Recettes en brouillon' => DB::table('cash_entries')->where('status', 'brouillon')->whereNull('cancelled_at')->count(),
                'Bulletins en attente de quittance' => DB::table('payment_slips')->where('status', 'en_attente')->whereNull('cancelled_at')->count(),
                'Versements non accusés' => DB::table('cash_remittances')->where('status', 'verse')->whereNull('cancelled_at')->count(),
                'Décharges de tickets non soldées' => DB::table('ticket_discharges')->where('status', 'remis')->whereNull('cancelled_at')->count(),
            ];
        }

        return $out;
    }

    /**
     * @param  array{report_date: string, notes?: ?string, bills?: array<string, int|string|null>, cheques?: float|string|null, counts?: array<int, int|string|null>, people?: array<string, ?string>}  $data
     */
    public function create(string $type, array $data, User $by): CashReport
    {
        abort_unless(isset(self::TYPES[$type]), 404);
        $t = self::TYPES[$type];
        $date = (string) $data['report_date'];
        $theory = $this->theoretical($type, $date);

        $bills = [];
        $counted = 0.0;
        if ($t['cash']) {
            foreach (self::DENOMINATIONS as $den) {
                $n = (int) ($data['bills'][$den] ?? 0);
                if ($n < 0) {
                    throw ValidationException::withMessages(["bills.$den" => 'Nombre invalide.']);
                }
                if ($n) {
                    $bills[$den] = $n;
                    $counted += $n * (float) $den;
                }
            }
            $cheques = round((float) ($data['cheques'] ?? 0), 2);
            $counted = round($counted + $cheques, 2);
        }
        $stock = [];
        if ($t['stock']) {
            foreach ($theory['stock'] as $v) {
                $c = $data['counts'][$v['id']] ?? null;
                if ($c === null || $c === '') {
                    throw ValidationException::withMessages(["counts.{$v['id']}" => 'Saisissez la quantité comptée.']);
                }
                $stock[] = $v + ['counted' => (int) $c, 'difference' => (int) $c - $v['theoretical'], 'value_difference' => round(((int) $c - $v['theoretical']) * $v['unit_value'], 2)];
            }
        }
        $people = array_map(fn ($p) => trim((string) $p), $data['people'] ?? []);
        if ($type === 'verification' && ($people['verifier'] ?? '') === '') {
            throw ValidationException::withMessages(['people.verifier' => 'Indiquez le vérificateur.']);
        }
        if ($type === 'remise' && (($people['outgoing'] ?? '') === '' || ($people['incoming'] ?? '') === '')) {
            throw ValidationException::withMessages(['people.incoming' => 'Indiquez le régisseur sortant et le régisseur entrant.']);
        }

        return DB::transaction(function () use ($type, $t, $date, $theory, $bills, $counted, $stock, $people, $data, $by) {
            $r = CashReport::create([
                'number' => $this->numbers->next('cash_reports', 'number', $t['prefix'], (int) substr($date, 0, 4)),
                'type' => $type, 'report_date' => $date, 'year' => (int) substr($date, 0, 4), 'period' => self::period($type, $date),
                'theoretical_balance' => $theory['balance'], 'counted_balance' => $counted, 'difference' => $t['cash'] ? round($counted - $theory['balance'], 2) : 0,
                'data' => ['bills' => $bills, 'cheques' => round((float) ($data['cheques'] ?? 0), 2), 'stock' => $stock, 'people' => $people, 'pending' => $theory['pending'] ?? []],
                'observations' => $data['notes'] ?? null, 'created_by' => $by->id,
            ]);
            activity('regie')->causedBy($by)->performedOn($r)->withProperties(['ecart' => $r->difference])->log($t['fr']);

            return $r;
        });
    }

    /** PV attendus et absents (pour le centre de contrôle). */
    public function missing(?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $out = [];
        $opening = Ledger::opening()['date'];
        $last = $today->subMonth();
        if ($last->endOfMonth()->toDateString() >= $opening
            && ! CashReport::active()->where('type', 'arrete')->where('year', $last->year)->where('period', $last->month)->exists()) {
            $out[] = sprintf('PV d’arrêté de caisse de %s %d', Ledger::MONTHS[$last->month], $last->year);
        }
        $q = $today->subQuarter();
        foreach (['verification', 'recolement'] as $type) {
            if ($q->endOfQuarter()->toDateString() >= $opening
                && ! CashReport::active()->where('type', $type)->where('year', $q->year)->where('period', $q->quarter)->exists()) {
                $out[] = sprintf('%s du T%d %d', self::TYPES[$type]['fr'], $q->quarter, $q->year);
            }
        }

        return $out;
    }
}
