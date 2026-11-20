<?php

namespace App\Domain\Tnb\Actions;

use App\Domain\Caisse\Models\PaymentSlip;
use App\Domain\Caisse\Models\TaxDeclaration;
use App\Domain\Caisse\Services\NumberGenerator;
use App\Domain\Tnb\Models\Parcel;
use App\Domain\Tnb\Services\TnbCalculator;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Déclarer / émettre la TNB de plusieurs années — Flask tnb.tnb_multi_declarations.
 *
 * Pour chaque année choisie, calcul à la date de déclaration :
 *   - déjà payée ➜ ignorée ; bulletin déjà en attente ➜ ignorée ;
 *   - déclaration existante sans bulletin valable (rejeté ou absent) ➜ recalculée et réémise
 *     (les bulletins rejetés sont annulés avec motif au lieu d'être supprimés) ;
 *   - année exonérée ➜ ignorée ;
 *   - sinon nouvelle déclaration « DCL{année}{n°} », échéance 28/02.
 * Total < 200 DH ➜ « sous seuil » (pas de bulletin). Un bulletin « en attente » par déclaration émise,
 * tous sous le même n° de BV (saisi, ou automatique si vide — écart : Flask n'en créait pas sans n°).
 */
class DeclareTnbYears
{
    public function __construct(protected TnbCalculator $calculator, protected NumberGenerator $numbers) {}

    /**
     * @param  list<int>  $years
     * @return array{declarations: list<TaxDeclaration>, slips: int, skipped: list<int>, slip_number: ?string, total: float}
     */
    public function handle(Parcel $parcel, array $years, User $agent, array $options = []): array
    {
        $years = array_values(array_unique(array_map('intval', $years)));
        sort($years);
        if (! $years) {
            throw ValidationException::withMessages(['years' => 'Aucune année sélectionnée.']);
        }

        return DB::transaction(function () use ($parcel, $years, $agent, $options) {
            Parcel::whereKey($parcel->id)->lockForUpdate()->first();
            $declaredOn = $options['declared_on'] ?? CarbonImmutable::today()->toDateString();
            $slipNumber = trim((string) ($options['slip_number'] ?? ''));
            if ($slipNumber !== '' && PaymentSlip::where('slip_number', $slipNumber)->whereNull('cancelled_at')->exists()) {
                throw ValidationException::withMessages(['slip_number' => "Le bulletin n° {$slipNumber} existe déjà."]);
            }
            $permits = $this->calculator->constructionPermits($parcel);

            $created = [];
            $skipped = [];
            $slips = 0;
            $total = 0.0;
            foreach ($years as $year) {
                $existing = TaxDeclaration::where('module', 'TNB')->where('taxable_type', 'parcel')->where('taxable_id', $parcel->id)
                    ->where('year', $year)->where('status', '!=', 'annule')->whereNull('cancelled_at')->lockForUpdate()->first();

                if ($existing && ($existing->status === 'paye'
                        || $existing->paymentSlips()->where('status', 'en_attente')->whereNull('cancelled_at')->exists())) {
                    $skipped[] = $year;

                    continue;
                }
                if (! $existing && TnbCalculator::isExempt($permits, $year)) {
                    $skipped[] = $year;

                    continue;
                }

                $r = $this->calculator->computeYear((string) ($parcel->zone ?? 'A'), (float) $parcel->area, $year, $declaredOn, $parcel->id);
                $status = $r['total'] < TnbCalculator::BELOW_THRESHOLD ? 'sous_seuil' : 'emis';
                $values = [
                    'base_amount' => (float) $parcel->area, 'rate' => $r['taux'], 'principal' => $r['principal'],
                    'late_penalty' => $r['penalite'], 'surcharge' => $r['majoration'], 'non_filing_fine' => $r['amende'],
                    'total' => $r['total'], 'status' => $status, 'declared_on' => $declaredOn, 'agent_id' => $agent->id,
                ];

                if ($existing) {
                    foreach ($existing->paymentSlips()->where('status', 'rejete')->whereNull('cancelled_at')->get() as $rejected) {
                        $rejected->cancel($agent, 'Remplacé lors de la réémission de la déclaration');
                    }
                    $existing->update($values);
                    $declaration = $existing;
                } else {
                    $declaration = TaxDeclaration::create($values + [
                        'number' => $this->numbers->next('tax_declarations', 'number', 'DCL'),
                        'module' => 'TNB', 'taxable_type' => 'parcel', 'taxable_id' => $parcel->id,
                        'taxpayer_id' => $parcel->taxpayer_id, 'municipality_id' => $parcel->municipality_id,
                        'year' => $year, 'quarter' => 0, 'due_on' => sprintf('%d-02-28', $year),
                    ]);
                }
                $created[] = $declaration;
                $total += $r['total'];

                if ($status === 'emis') {
                    if ($slipNumber === '') {
                        $slipNumber = $this->numbers->next('payment_slips', 'slip_number', 'BUL');
                    }
                    PaymentSlip::create([
                        'slip_number' => $slipNumber, 'tax_declaration_id' => $declaration->id,
                        'taxpayer_id' => $parcel->taxpayer_id, 'municipality_id' => $parcel->municipality_id,
                        'amount' => $r['total'], 'payment_mode' => 'bulletin_manuel', 'paid_on' => $declaredOn,
                        'status' => 'en_attente', 'agent_id' => $agent->id, 'notes' => "TNB {$year} — terrain {$parcel->number}",
                    ]);
                    $slips++;
                }
            }

            if ($created) {
                activity('pieces')->causedBy($agent)->performedOn($parcel)
                    ->withProperties(['annees' => collect($created)->pluck('year'), 'total' => round($total, 2), 'bulletin' => $slipNumber ?: null])
                    ->log("Déclaration TNB terrain {$parcel->number} — ".count($created).' année(s)');
            }

            return ['declarations' => $created, 'slips' => $slips, 'skipped' => $skipped, 'slip_number' => $slips ? $slipNumber : null, 'total' => round($total, 2)];
        });
    }
}
