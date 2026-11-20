<?php

namespace App\Domain\Caisse\Services;

use App\Domain\Caisse\Models\PaymentSlip;
use App\Domain\Caisse\Models\TaxDeclaration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Caisse du régisseur — Flask modules/bulletins.py.
 *
 * - Valider : n° de quittance obligatoire ; le bulletin et tous les bulletins « en attente » portant le
 *   même n° de BV passent « payé », leurs déclarations aussi (date de paiement = date de quittance).
 * - Rejeter : motif obligatoire ; la déclaration redevient « émise ».
 * - Corriger : nouveau n° de BV, mode, notes ; le bulletin repart « en attente ».
 * - Annuler (au lieu de supprimer, pièces immuables) : motif obligatoire ; la déclaration peut être
 *   annulée aussi, ce qui libère les périodes.
 * Un n° de quittance déjà donné à un autre bulletin (autre n° de BV) est refusé.
 */
class CashierService
{
    public function validate(PaymentSlip $slip, string $receiptNumber, string $receiptDate, User $cashier): int
    {
        return $this->validateMany([$slip->id], $receiptNumber, $receiptDate, $cashier, cascadeSameSlipNumber: true);
    }

    /**
     * @param  array<int>  $slipIds
     */
    public function validateMany(array $slipIds, string $receiptNumber, string $receiptDate, User $cashier, bool $cascadeSameSlipNumber = false): int
    {
        $receiptNumber = trim($receiptNumber);
        if ($receiptNumber === '') {
            throw ValidationException::withMessages(['receipt_number' => 'Le numéro de quittance est obligatoire.']);
        }
        // Clôture mensuelle de la régie : pas de quittance datée d'un mois clôturé.
        \App\Domain\Regie\Services\MonthLock::assertOpen($receiptDate, 'receipt_date');

        return DB::transaction(function () use ($slipIds, $receiptNumber, $receiptDate, $cashier, $cascadeSameSlipNumber) {
            $slips = PaymentSlip::whereIn('id', $slipIds)->where('status', 'en_attente')->whereNull('cancelled_at')->lockForUpdate()->get();
            if ($slips->isEmpty()) {
                throw ValidationException::withMessages(['receipt_number' => 'Aucun bulletin en attente à valider.']);
            }
            if ($cascadeSameSlipNumber) {
                $slips = $slips->merge(PaymentSlip::whereIn('slip_number', $slips->pluck('slip_number')->filter())
                    ->where('status', 'en_attente')->whereNull('cancelled_at')->whereNotIn('id', $slips->pluck('id'))
                    ->lockForUpdate()->get());
            }

            $usedElsewhere = PaymentSlip::where('receipt_number', $receiptNumber)->whereNull('cancelled_at')
                ->whereNotIn('slip_number', $slips->pluck('slip_number')->filter()->all())->lockForUpdate()->exists();
            if ($usedElsewhere) {
                throw ValidationException::withMessages(['receipt_number' => "La quittance n° {$receiptNumber} a déjà été délivrée pour un autre bulletin."]);
            }

            foreach ($slips as $slip) {
                $slip->update(['status' => 'paye', 'cashier_id' => $cashier->id, 'receipt_number' => $receiptNumber, 'receipt_date' => $receiptDate]);
                TaxDeclaration::whereKey($slip->tax_declaration_id)->update(['status' => 'paye', 'paid_on' => $receiptDate]);
            }

            // Fourrière (Flask bulletins.valider) : le véhicule passe « en attente de sortie » une fois payé.
            $impounds = TaxDeclaration::whereIn('id', $slips->pluck('tax_declaration_id'))->where('module', 'FOURRIERE')
                ->where('taxable_type', 'impound_case')->pluck('taxable_id');
            if ($impounds->isNotEmpty()) {
                \App\Domain\Fourriere\Models\ImpoundCase::whereIn('id', $impounds)->where('status', 'en_fourriere')
                    ->update(['status' => 'en_attente_sortie', 'paid_on' => $receiptDate]);
            }

            activity('pieces')->causedBy($cashier)
                ->withProperties(['quittance' => $receiptNumber, 'bulletins' => $slips->pluck('slip_number')->unique()->values(), 'montant' => $slips->sum('amount')])
                ->log("Quittance {$receiptNumber} — {$slips->count()} bulletin(s) soldé(s)");

            return $slips->count();
        });
    }

    public function reject(PaymentSlip $slip, string $reason, User $cashier): void
    {
        $reason = trim($reason) ?: 'Non précisé';
        DB::transaction(function () use ($slip, $reason, $cashier) {
            $this->ensurePending($slip);
            $slip->update(['status' => 'rejete', 'rejection_reason' => $reason, 'cashier_id' => $cashier->id]);
            TaxDeclaration::whereKey($slip->tax_declaration_id)->where('status', '!=', 'paye')->update(['status' => 'emis']);
            activity('pieces')->causedBy($cashier)->performedOn($slip)->withProperties(['motif' => $reason])
                ->log("Rejet du bulletin {$slip->slip_number}");
        });
    }

    public function correct(PaymentSlip $slip, string $slipNumber, string $paymentMode, ?string $notes, User $by): void
    {
        $slipNumber = trim($slipNumber);
        if ($slipNumber === '') {
            throw ValidationException::withMessages(['slip_number' => 'Le numéro de bulletin est obligatoire.']);
        }
        if ($slip->status === 'paye' || $slip->isCancelled()) {
            throw ValidationException::withMessages(['slip_number' => 'Impossible de modifier un bulletin déjà payé ou annulé.']);
        }
        $slip->update(['slip_number' => $slipNumber, 'payment_mode' => $paymentMode, 'notes' => $notes,
            'status' => 'en_attente', 'rejection_reason' => null]);
        activity('pieces')->causedBy($by)->performedOn($slip)->log("Bulletin corrigé et réémis : {$slipNumber}");
    }

    public function cancel(PaymentSlip $slip, string $reason, User $by, bool $cancelDeclaration = false): void
    {
        if ($slip->status === 'paye') {
            throw ValidationException::withMessages(['reason' => 'Un bulletin quittancé ne s’annule pas ici.']);
        }
        DB::transaction(function () use ($slip, $reason, $by, $cancelDeclaration) {
            $slip->cancel($by, $reason);
            $declaration = TaxDeclaration::find($slip->tax_declaration_id);
            if (! $declaration || $declaration->status === 'paye' || $declaration->isCancelled()) {
                return;
            }
            if ($cancelDeclaration) {
                $declaration->cancel($by, $reason);
            } else {
                $declaration->update(['status' => 'emis']);
            }
        });
    }

    protected function ensurePending(PaymentSlip $slip): void
    {
        if ($slip->status !== 'en_attente' || $slip->isCancelled()) {
            throw ValidationException::withMessages(['slip' => 'Ce bulletin n’est plus en attente.']);
        }
    }
}
