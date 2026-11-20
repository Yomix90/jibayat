<?php

namespace App\Domain\Locations\Actions;

use App\Domain\Caisse\Models\PaymentSlip;
use App\Domain\Caisse\Models\TaxDeclaration;
use App\Domain\Caisse\Services\NumberGenerator;
use App\Domain\Locations\Models\Lease;
use App\Domain\Locations\Services\LeaseCalculator;
use App\Models\User;
use App\Support\PyMath;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Encaisser des loyers — Flask location.loc_payer : une déclaration « émise » couvrant les mois choisis
 * + un bulletin de versement « en attente » de validation par le régisseur.
 *
 * Écart volontaire avec Flask : le montant est la SOMME DES TARIFS DE CHAQUE MOIS (ce que l'écran
 * affiche et ce que compte la situation du bail), et non « loyer actuel × nombre de mois ».
 * Les deux sont identiques tant que le tarif n'a pas changé sur la période.
 */
class CollectRent
{
    public function __construct(protected LeaseCalculator $calculator, protected NumberGenerator $numbers) {}

    /**
     * @param  list<string>  $months  mois « AAAA-MM » à encaisser
     */
    public function handle(Lease $lease, array $months, User $agent, array $options = []): TaxDeclaration
    {
        $months = array_values(array_unique($months));
        sort($months);
        if (! $months) {
            throw ValidationException::withMessages(['months' => 'Veuillez sélectionner au moins un mois.']);
        }

        return DB::transaction(function () use ($lease, $months, $agent, $options) {
            Lease::whereKey($lease->id)->lockForUpdate()->first();

            $today = isset($options['today']) ? CarbonImmutable::parse($options['today']) : CarbonImmutable::today();
            $available = collect($this->calculator->unpaidMonths($lease, $today))
                ->reject(fn ($m) => $m['est_emis'])->keyBy('mois');
            $invalid = array_diff($months, $available->keys()->all());
            if ($invalid) {
                throw ValidationException::withMessages(['months' => 'Mois déjà réglés, déjà émis ou hors bail : '.implode(', ', $invalid).'.']);
            }

            $slipNumber = trim((string) ($options['slip_number'] ?? ''));
            if ($slipNumber === '') {
                $slipNumber = $this->numbers->next('payment_slips', 'slip_number', 'BUL');
            } elseif (PaymentSlip::where('slip_number', $slipNumber)->whereNull('cancelled_at')->exists()) {
                throw ValidationException::withMessages(['slip_number' => "Le bulletin n° {$slipNumber} existe déjà. Utilisez un autre numéro."]);
            }

            $total = PyMath::round($available->only($months)->sum('tarif'), 2);
            $paidOn = $options['paid_on'] ?? $today->toDateString();
            $notes = trim((string) ($options['notes'] ?? ''));
            $monthList = implode(', ', $months);

            $declaration = TaxDeclaration::create([
                'number' => $this->numbers->next('tax_declarations', 'number', 'LOC-DECL'),
                'module' => 'LOCATION_LOCAUX',
                'taxable_type' => 'lease',
                'taxable_id' => $lease->id,
                'taxpayer_id' => $lease->taxpayer_id,
                'municipality_id' => $lease->municipality_id,
                'year' => (int) substr(end($months), 0, 4),
                'quarter' => 0,
                'base_amount' => $this->calculator->monthlyRent($lease),
                'rate' => 0,
                'principal' => $total,
                'total' => $total,
                'status' => 'emis',
                'declared_on' => $paidOn,
                'due_on' => $paidOn,
                'agent_id' => $agent->id,
                'notes' => $monthList.($notes !== '' ? " | {$notes}" : ''),
            ]);
            foreach ($months as $m) {
                $declaration->periods()->create(['period' => $m, 'amount' => $available[$m]['tarif']]);
            }

            PaymentSlip::create([
                'slip_number' => $slipNumber,
                'tax_declaration_id' => $declaration->id,
                'taxpayer_id' => $lease->taxpayer_id,
                'municipality_id' => $lease->municipality_id,
                'amount' => $total,
                'payment_mode' => $options['payment_mode'] ?? 'especes',
                'paid_on' => $paidOn,
                'status' => 'en_attente',
                'agent_id' => $agent->id,
                'notes' => 'Location locaux — '.count($months)." mois: {$monthList}",
            ]);

            activity('pieces')->causedBy($agent)->performedOn($declaration)
                ->withProperties(['bail' => $lease->number, 'mois' => $months, 'montant' => $total, 'bulletin' => $slipNumber])
                ->log("Émission loyers bail {$lease->number} — ".count($months).' mois');

            return $declaration;
        });
    }
}
