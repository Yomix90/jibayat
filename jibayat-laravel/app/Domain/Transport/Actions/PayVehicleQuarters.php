<?php

namespace App\Domain\Transport\Actions;

use App\Domain\Caisse\Models\PaymentSlip;
use App\Domain\Caisse\Models\TaxDeclaration;
use App\Domain\Caisse\Services\NumberGenerator;
use App\Domain\Transport\Models\Vehicle;
use App\Domain\Transport\Services\TransportCalculator;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Encaissement trimestriel stationnement + TPV — Flask stationnement.sta_payer.
 * Trimestres traités dans l'ordre chronologique ; un trimestre déjà déclaré (non annulé) est ignoré.
 * Une déclaration « DCL-STA… » par trimestre, un bulletin « en attente » par déclaration émise, même n° de BV
 * (généré si vide, comme pour la TNB). Total < 200 DH : « sous seuil », sans bulletin.
 */
class PayVehicleQuarters
{
    public function __construct(protected TransportCalculator $calculator, protected NumberGenerator $numbers) {}

    /**
     * @param  list<string>  $quarters  ['2025_3', '2025_4', …]
     * @return array{declarations: list<TaxDeclaration>, slips: int, skipped: list<string>, slip_number: ?string, total: float}
     */
    public function handle(Vehicle $vehicle, array $quarters, User $agent, array $options = []): array
    {
        $quarters = array_values(array_unique($quarters));
        sort($quarters);
        if (! $quarters) {
            throw ValidationException::withMessages(['quarters' => 'Aucun trimestre sélectionné.']);
        }

        return DB::transaction(function () use ($vehicle, $quarters, $agent, $options) {
            Vehicle::whereKey($vehicle->id)->lockForUpdate()->first();
            $paidOn = $options['paid_on'] ?? CarbonImmutable::today()->toDateString();
            $slipNumber = trim((string) ($options['slip_number'] ?? ''));
            if ($slipNumber !== '' && PaymentSlip::where('slip_number', $slipNumber)->whereNull('cancelled_at')->exists()) {
                throw ValidationException::withMessages(['slip_number' => "Le bulletin n° {$slipNumber} existe déjà."]);
            }

            $created = [];
            $skipped = [];
            $slips = 0;
            $total = 0.0;
            foreach ($quarters as $key) {
                if (! preg_match('/^(\d{4})_([1-4])$/', (string) $key, $m)) {
                    continue;
                }
                [$year, $quarter] = [(int) $m[1], (int) $m[2]];
                $exists = TaxDeclaration::where('module', TransportCalculator::MODULE)->where('taxable_type', 'vehicle')
                    ->where('taxable_id', $vehicle->id)->where('year', $year)->where('quarter', $quarter)
                    ->where('status', '!=', 'annule')->whereNull('cancelled_at')->lockForUpdate()->exists();
                if ($exists) {
                    $skipped[] = "T{$quarter} {$year}";

                    continue;
                }

                [$sta, $tpv] = $this->calculator->amounts($vehicle, $year, $quarter);
                $r = $this->calculator->computeQuarter($sta, $tpv, $year, $quarter, $paidOn);
                $declaration = TaxDeclaration::create([
                    'number' => $this->numbers->next('tax_declarations', 'number', 'DCL-STA'),
                    'module' => TransportCalculator::MODULE, 'taxable_type' => 'vehicle', 'taxable_id' => $vehicle->id,
                    'taxpayer_id' => $vehicle->taxpayer_id, 'municipality_id' => $vehicle->municipality_id,
                    'year' => $year, 'quarter' => $quarter, 'base_amount' => $r['tpv'], 'rate' => $r['sta'],
                    'principal' => $r['principal'], 'late_penalty' => $r['penalite'], 'surcharge' => $r['majoration'],
                    'non_filing_fine' => 0, 'total' => $r['total'], 'status' => $r['statut'],
                    'declared_on' => $paidOn, 'due_on' => $r['echeance'], 'agent_id' => $agent->id,
                ]);
                $created[] = $declaration;
                $total += $r['total'];

                if ($r['statut'] === 'emis') {
                    if ($slipNumber === '') {
                        $slipNumber = $this->numbers->next('payment_slips', 'slip_number', 'BUL');
                    }
                    PaymentSlip::create([
                        'slip_number' => $slipNumber, 'tax_declaration_id' => $declaration->id,
                        'taxpayer_id' => $vehicle->taxpayer_id, 'municipality_id' => $vehicle->municipality_id,
                        'amount' => $r['total'], 'payment_mode' => 'bulletin_manuel', 'paid_on' => $paidOn,
                        'status' => 'en_attente', 'agent_id' => $agent->id, 'notes' => "Transport T{$quarter} {$year} — {$vehicle->registration}",
                    ]);
                    $slips++;
                }
            }

            if ($created) {
                activity('pieces')->causedBy($agent)->performedOn($vehicle)
                    ->withProperties(['trimestres' => collect($created)->map(fn ($d) => "T{$d->quarter} {$d->year}"), 'total' => round($total, 2), 'bulletin' => $slips ? $slipNumber : null])
                    ->log("Encaissement transport {$vehicle->registration} — ".count($created).' trimestre(s)');
            }

            return ['declarations' => $created, 'slips' => $slips, 'skipped' => $skipped, 'slip_number' => $slips ? $slipNumber : null, 'total' => round($total, 2)];
        });
    }
}
