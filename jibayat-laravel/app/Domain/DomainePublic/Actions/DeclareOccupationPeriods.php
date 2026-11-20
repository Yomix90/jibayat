<?php

namespace App\Domain\DomainePublic\Actions;

use App\Domain\Caisse\Models\PaymentSlip;
use App\Domain\Caisse\Models\TaxDeclaration;
use App\Domain\Caisse\Services\NumberGenerator;
use App\Domain\DomainePublic\Models\PublicOccupation;
use App\Domain\DomainePublic\Services\OccupationCalculator;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Liquidation de l'occupation du domaine public — Flask occupation.odp_declarer.
 * Périodes « AAAA_T » (T = 1..4) ou « AAAA_0 » (année entière). Une déclaration « DCL-ODP… » par période, montant =
 * tarif × superficie, sans pénalité ; < 100 DH ➜ « sous seuil » sans bulletin ; un bulletin « en attente » par période
 * émise, même n° de BV (généré si vide). Une période déjà payée ou émise est refusée (Flask ne vérifiait pas).
 */
class DeclareOccupationPeriods
{
    public function __construct(protected OccupationCalculator $calculator, protected NumberGenerator $numbers) {}

    /**
     * @param  list<string>  $periods
     * @return array{declarations: list<TaxDeclaration>, slips: int, slip_number: ?string, total: float}
     */
    public function handle(PublicOccupation $o, array $periods, User $agent, array $options = []): array
    {
        $periods = array_values(array_unique($periods));
        sort($periods);
        if (! $periods) {
            throw ValidationException::withMessages(['periods' => 'Aucune période sélectionnée.']);
        }

        return DB::transaction(function () use ($o, $periods, $agent, $options) {
            PublicOccupation::whereKey($o->id)->lockForUpdate()->first();
            $declaredOn = $options['declared_on'] ?? CarbonImmutable::today()->toDateString();
            $payable = $this->calculator->payable($o);
            $allowed = array_merge(array_map(fn ($q) => "{$q['annee']}_{$q['trimestre']}", $payable['quarters']), array_map(fn ($y) => "{$y}_0", $payable['years']));
            $years = array_map(fn ($p) => (int) explode('_', $p)[0], array_filter($periods, fn ($p) => str_ends_with($p, '_0')));
            $invalid = array_filter($periods, fn ($p) => ! in_array($p, $allowed, true)
                || (! str_ends_with($p, '_0') && in_array((int) explode('_', $p)[0], $years, true)));
            if ($invalid) {
                throw ValidationException::withMessages(['periods' => 'Périodes déjà payées, déjà émises ou en double : '.implode(', ', $invalid).'.']);
            }
            $slipNumber = trim((string) ($options['slip_number'] ?? ''));
            if ($slipNumber !== '' && PaymentSlip::where('slip_number', $slipNumber)->whereNull('cancelled_at')->exists()) {
                throw ValidationException::withMessages(['slip_number' => "Le bulletin n° {$slipNumber} existe déjà."]);
            }

            $created = [];
            $slips = 0;
            $total = 0.0;
            foreach ($periods as $p) {
                [$year, $quarter] = array_map('intval', explode('_', $p));
                $r = $this->calculator->computePeriod($o, $year, $quarter);
                if ($r['total'] <= 0) {
                    continue;
                }
                $d = TaxDeclaration::create([
                    'number' => $this->numbers->next('tax_declarations', 'number', 'DCL-ODP'),
                    'module' => OccupationCalculator::MODULE, 'taxable_type' => 'public_occupation', 'taxable_id' => $o->id,
                    'taxpayer_id' => $o->taxpayer_id, 'municipality_id' => $o->municipality_id,
                    'year' => $year, 'quarter' => $quarter, 'base_amount' => (float) $o->area, 'rate' => $r['taux'],
                    'principal' => $r['principal'], 'late_penalty' => 0, 'surcharge' => 0, 'non_filing_fine' => 0,
                    'total' => $r['total'], 'status' => $r['statut'], 'declared_on' => $declaredOn, 'due_on' => $r['echeance'], 'agent_id' => $agent->id,
                ]);
                $created[] = $d;
                $total += $r['total'];
                if ($r['statut'] === 'emis') {
                    if ($slipNumber === '') {
                        $slipNumber = $this->numbers->next('payment_slips', 'slip_number', 'BUL');
                    }
                    PaymentSlip::create([
                        'slip_number' => $slipNumber, 'tax_declaration_id' => $d->id, 'taxpayer_id' => $o->taxpayer_id,
                        'municipality_id' => $o->municipality_id, 'amount' => $r['total'], 'payment_mode' => 'bulletin_manuel',
                        'paid_on' => $declaredOn, 'status' => 'en_attente', 'agent_id' => $agent->id,
                        'notes' => 'ODP '.($quarter ? "T{$quarter} " : 'année ')."{$year} — {$o->location}",
                    ]);
                    $slips++;
                }
            }
            if ($created) {
                activity('pieces')->causedBy($agent)->performedOn($o)
                    ->withProperties(['periodes' => $periods, 'total' => round($total, 2), 'bulletin' => $slips ? $slipNumber : null])
                    ->log("Liquidation ODP occupation {$o->number} — ".count($created).' période(s)');
            }

            return ['declarations' => $created, 'slips' => $slips, 'slip_number' => $slips ? $slipNumber : null, 'total' => round($total, 2)];
        });
    }
}
