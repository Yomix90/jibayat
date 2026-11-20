<?php

namespace App\Domain\Boissons\Actions;

use App\Domain\Boissons\Models\BeverageOutlet;
use App\Domain\Boissons\Services\BeverageCalculator;
use App\Domain\Caisse\Models\PaymentSlip;
use App\Domain\Caisse\Models\TaxDeclaration;
use App\Domain\Caisse\Services\NumberGenerator;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Déclaration trimestrielle du chiffre d'affaires — Flask tdb.tdb_declarer.
 * Trimestres traités dans l'ordre chronologique ; un trimestre déjà déclaré (non annulé) est ignoré.
 * Une déclaration « DCL-TDB… » par trimestre ; un bulletin « en attente » par déclaration émise, tous sous le même n° de BV
 * (n° généré si vide — même règle que la TNB, validée). Total < 200 DH : « sous seuil », sans bulletin.
 */
class DeclareBeverageQuarters
{
    public function __construct(protected BeverageCalculator $calculator, protected NumberGenerator $numbers) {}

    /**
     * @param  array<string, float|string|null>  $bases  ['2025_1' => 12345.67, …]
     * @return array{declarations: list<TaxDeclaration>, slips: int, skipped: list<string>, slip_number: ?string, total: float}
     */
    public function handle(BeverageOutlet $outlet, array $bases, User $agent, array $options = []): array
    {
        $keys = array_keys($bases);
        sort($keys);   // « annee_trim » : ordre lexicographique = chronologique (Flask)
        if (! $keys) {
            throw ValidationException::withMessages(['quarters' => 'Aucun trimestre sélectionné.']);
        }

        return DB::transaction(function () use ($outlet, $bases, $keys, $agent, $options) {
            BeverageOutlet::whereKey($outlet->id)->lockForUpdate()->first();
            $declaredOn = $options['declared_on'] ?? CarbonImmutable::today()->toDateString();
            $slipNumber = trim((string) ($options['slip_number'] ?? ''));
            if ($slipNumber !== '' && PaymentSlip::where('slip_number', $slipNumber)->whereNull('cancelled_at')->exists()) {
                throw ValidationException::withMessages(['slip_number' => "Le bulletin n° {$slipNumber} existe déjà."]);
            }

            $created = [];
            $skipped = [];
            $slips = 0;
            $total = 0.0;
            foreach ($keys as $key) {
                if (! preg_match('/^(\d{4})_([1-4])$/', (string) $key, $m)) {
                    continue;
                }
                [$year, $quarter] = [(int) $m[1], (int) $m[2]];
                $exists = TaxDeclaration::where('module', BeverageCalculator::MODULE)->where('taxable_type', 'beverage_outlet')
                    ->where('taxable_id', $outlet->id)->where('year', $year)->where('quarter', $quarter)
                    ->where('status', '!=', 'annule')->whereNull('cancelled_at')->lockForUpdate()->exists();
                if ($exists) {
                    $skipped[] = "T{$quarter} {$year}";

                    continue;
                }

                // Taux de l'arrêté fiscal en vigueur pour ce trimestre.
                $rate = $this->calculator->rateForQuarter($outlet, $year, $quarter);
                $r = $this->calculator->computeQuarter((float) ($bases[$key] ?: 0), $rate, $year, $quarter, $declaredOn, $outlet->id);
                $declaration = TaxDeclaration::create([
                    'number' => $this->numbers->next('tax_declarations', 'number', 'DCL-TDB'),
                    'module' => BeverageCalculator::MODULE, 'taxable_type' => 'beverage_outlet', 'taxable_id' => $outlet->id,
                    'taxpayer_id' => $outlet->taxpayer_id, 'municipality_id' => $outlet->municipality_id,
                    'year' => $year, 'quarter' => $quarter, 'base_amount' => $r['base'], 'rate' => $r['taux'],
                    'principal' => $r['principal'], 'late_penalty' => $r['penalite'], 'surcharge' => $r['majoration'],
                    'non_filing_fine' => $r['amende'], 'total' => $r['total'], 'status' => $r['statut'],
                    'declared_on' => $declaredOn, 'due_on' => $r['echeance'], 'agent_id' => $agent->id,
                ]);
                $created[] = $declaration;
                $total += $r['total'];

                if ($r['statut'] === 'emis') {
                    if ($slipNumber === '') {
                        $slipNumber = $this->numbers->next('payment_slips', 'slip_number', 'BUL');
                    }
                    PaymentSlip::create([
                        'slip_number' => $slipNumber, 'tax_declaration_id' => $declaration->id,
                        'taxpayer_id' => $outlet->taxpayer_id, 'municipality_id' => $outlet->municipality_id,
                        'amount' => $r['total'], 'payment_mode' => 'bulletin_manuel', 'paid_on' => $declaredOn,
                        'status' => 'en_attente', 'agent_id' => $agent->id, 'notes' => "TDB T{$quarter} {$year} — {$outlet->name}",
                    ]);
                    $slips++;
                }
            }

            if ($created) {
                activity('pieces')->causedBy($agent)->performedOn($outlet)
                    ->withProperties(['trimestres' => collect($created)->map(fn ($d) => "T{$d->quarter} {$d->year}"), 'total' => round($total, 2), 'bulletin' => $slips ? $slipNumber : null])
                    ->log("Déclaration TDB {$outlet->name} — ".count($created).' trimestre(s)');
            }

            return ['declarations' => $created, 'slips' => $slips, 'skipped' => $skipped, 'slip_number' => $slips ? $slipNumber : null, 'total' => round($total, 2)];
        });
    }
}
