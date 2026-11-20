<?php

namespace App\Domain\Regie\Services;

use App\Domain\Regie\Models\CashClosure;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Clôture mensuelle (verrou du mois) : une fois le mois clôturé, aucune recette, validation, annulation
 * ni versement ne peut être daté de ce mois. La réouverture est motivée, réservée à l'administration,
 * et conservée au journal.
 */
class MonthLock
{
    public static function closure(int $year, int $month): ?CashClosure
    {
        return CashClosure::active()->where('year', $year)->where('month', $month)->latest('id')->first();
    }

    public static function isClosed(string $date): bool
    {
        $d = CarbonImmutable::parse($date);

        return CashClosure::active()->where('year', $d->year)->where('month', $d->month)->exists();
    }

    /** @throws ValidationException */
    public static function assertOpen(?string $date, string $field = 'date'): void
    {
        if ($date && self::isClosed($date)) {
            $d = CarbonImmutable::parse($date);
            throw ValidationException::withMessages([$field => sprintf('Le mois %s %d est clôturé : aucune opération ne peut y être enregistrée.',
                Ledger::MONTHS[$d->month], $d->year)]);
        }
    }

    /** Points bloquants avant la clôture. */
    public function blockers(int $year, int $month): array
    {
        $start = CarbonImmutable::create($year, $month, 1);
        $end = $start->endOfMonth()->toDateString();
        $out = [];
        $drafts = DB::table('cash_entries')->where('status', 'brouillon')->whereNull('cancelled_at')->whereBetween('entry_date', [$start->toDateString(), $end])->count();
        if ($drafts) {
            $out[] = "$drafts recette(s) en brouillon à valider ou annuler.";
        }
        if ($start->endOfMonth()->isFuture()) {
            $out[] = 'Le mois n’est pas terminé.';
        }
        $prev = $start->subMonth();
        $opening = Ledger::opening()['date'];
        if ($prev->endOfMonth()->toDateString() >= $opening && ! self::closure($prev->year, $prev->month)) {
            $out[] = sprintf('Le mois précédent (%s %d) n’est pas clôturé.', Ledger::MONTHS[$prev->month], $prev->year);
        }

        return $out;
    }

    public function close(int $year, int $month, User $by): CashClosure
    {
        if (self::closure($year, $month)) {
            throw ValidationException::withMessages(['month' => 'Ce mois est déjà clôturé.']);
        }
        if ($b = $this->blockers($year, $month)) {
            throw ValidationException::withMessages(['month' => implode(' ', $b)]);
        }
        $ledger = app(Ledger::class);
        $book = $ledger->cashBook($year, $month);
        $state = $ledger->monthlyState($year, $month, true);

        $closure = CashClosure::create([
            'year' => $year, 'month' => $month, 'opening_balance' => $book['carried'], 'receipts' => $book['total_receipts'],
            'remittances' => $book['total_remittances'], 'closing_balance' => $book['closing'], 'snapshot' => $state,
            'closed_at' => now(), 'closed_by' => $by->id,
        ]);
        activity('regie')->causedBy($by)->performedOn($closure)->withProperties(['mois' => "$month/$year", 'solde' => $book['closing']])->log('Clôture mensuelle');

        return $closure;
    }

    public function reopen(CashClosure $closure, User $by, string $reason): void
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Le motif de réouverture est obligatoire.']);
        }
        $next = CarbonImmutable::create($closure->year, $closure->month, 1)->addMonth();
        if (self::closure($next->year, $next->month)) {
            throw ValidationException::withMessages(['reason' => 'Rouvrez d’abord le mois suivant, qui est clôturé.']);
        }
        $closure->update(['reopened_at' => now(), 'reopened_by' => $by->id, 'reopen_reason' => $reason]);
        activity('regie')->causedBy($by)->performedOn($closure)->withProperties(['mois' => "$closure->month/$closure->year", 'motif' => $reason])->log('Réouverture du mois');
    }
}
