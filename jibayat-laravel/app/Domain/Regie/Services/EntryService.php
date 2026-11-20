<?php

namespace App\Domain\Regie\Services;

use App\Domain\Caisse\Services\NumberGenerator;
use App\Domain\Regie\Models\CashEntry;
use App\Models\Rubric;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saisie des recettes de la régie : chaque ligne est d'abord un brouillon (modifiable), puis validée par le
 * régisseur (pièce officielle : elle ne se modifie plus, elle s'annule avec un motif).
 */
class EntryService
{
    public function __construct(protected NumberGenerator $numbers) {}

    /**
     * @param  list<array{rubric_id: int|string, payment_mode: string, amount?: string|float|null, quantity?: string|float|null, unit_price?: string|float|null, reference?: ?string, payer?: ?string, description?: ?string}>  $lines
     * @return list<CashEntry>
     */
    public function createDrafts(string $date, array $lines, User $by, string $source = 'saisie'): array
    {
        MonthLock::assertOpen($date, 'date');
        $lines = array_values(array_filter($lines, fn ($l) => ($l['rubric_id'] ?? '') !== '' || ($l['amount'] ?? '') !== ''));
        if (! $lines) {
            throw ValidationException::withMessages(['lines' => 'Saisissez au moins une recette.']);
        }

        return DB::transaction(function () use ($date, $lines, $by, $source) {
            $out = [];
            foreach ($lines as $i => $l) {
                $out[] = CashEntry::create($this->values($l, $i) + [
                    'number' => $this->numbers->next('cash_entries', 'number', 'REC', (int) substr($date, 0, 4)),
                    'entry_date' => $date, 'status' => 'brouillon', 'source' => $source, 'created_by' => $by->id,
                ]);
            }
            activity('regie')->causedBy($by)->withProperties(['date' => $date, 'lignes' => count($out), 'total' => array_sum(array_map(fn ($e) => (float) $e->amount, $out))])
                ->log('Saisie de recettes (brouillon)');

            return $out;
        });
    }

    /** Montant = quantité × prix unitaire quand les deux sont donnés. */
    protected function values(array $l, int $i): array
    {
        $rubric = Rubric::find($l['rubric_id'] ?? null);
        $errors = [];
        if (! $rubric) {
            $errors["lines.$i.rubric_id"] = 'Choisissez la rubrique.';
        }
        $mode = $l['payment_mode'] ?? 'especes';
        if (! in_array($mode, Ledger::ENTRY_MODES, true)) {
            $errors["lines.$i.payment_mode"] = 'Mode de paiement invalide.';
        }
        $qty = is_numeric($l['quantity'] ?? null) ? round((float) $l['quantity'], 2) : null;
        $unit = is_numeric($l['unit_price'] ?? null) ? round((float) $l['unit_price'], 2) : null;
        $amount = is_numeric($l['amount'] ?? null) ? round((float) $l['amount'], 2) : null;
        if ($qty !== null && $unit !== null) {
            $amount = round($qty * $unit, 2);
        }
        if ($amount === null || $amount <= 0) {
            $errors["lines.$i.amount"] = 'Montant invalide.';
        }
        if (in_array($mode, ['cheque', 'virement', 'virement_ministere'], true) && trim((string) ($l['reference'] ?? '')) === '') {
            $errors["lines.$i.reference"] = 'Référence obligatoire (n° de chèque ou d’ordre de virement).';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return ['rubric_id' => $rubric->id, 'payment_mode' => $mode, 'quantity' => $qty, 'unit_price' => $unit, 'amount' => $amount,
            'reference' => trim((string) ($l['reference'] ?? '')) ?: null, 'payer' => trim((string) ($l['payer'] ?? '')) ?: null,
            'description' => trim((string) ($l['description'] ?? '')) ?: null];
    }

    public function updateDraft(CashEntry $e, array $l, User $by): void
    {
        if ($e->status !== 'brouillon' || $e->isCancelled()) {
            throw ValidationException::withMessages(['entry' => 'Seul un brouillon peut être modifié.']);
        }
        MonthLock::assertOpen($e->entry_date->toDateString(), 'entry');
        $e->update($this->values($l, 0));
        activity('regie')->causedBy($by)->performedOn($e)->log('Brouillon de recette modifié');
    }

    /** Validation par le régisseur (une ou plusieurs lignes). */
    public function validate(array $ids, User $by): int
    {
        return DB::transaction(function () use ($ids, $by) {
            $entries = CashEntry::whereIn('id', $ids)->where('status', 'brouillon')->whereNull('cancelled_at')->lockForUpdate()->get();
            foreach ($entries as $e) {
                MonthLock::assertOpen($e->entry_date->toDateString(), 'entries');
                $e->update(['status' => 'valide', 'validated_by' => $by->id, 'validated_at' => now()]);
            }
            if ($entries->isNotEmpty()) {
                activity('regie')->causedBy($by)->withProperties(['pieces' => $entries->pluck('number')->all(), 'total' => $entries->sum('amount')])
                    ->log('Validation de recettes');
            }

            return $entries->count();
        });
    }

    public function cancel(CashEntry $e, User $by, string $reason): void
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Le motif est obligatoire.']);
        }
        MonthLock::assertOpen($e->entry_date->toDateString(), 'reason');
        if (DB::table('ticket_discharges')->where('cash_entry_id', $e->id)->whereNull('cancelled_at')->exists()) {
            throw ValidationException::withMessages(['reason' => 'Cette recette provient d’une décharge de tickets soldée : modifiez la décharge.']);
        }
        $e->cancel($by, $reason);
    }
}
