<?php

namespace App\Domain\Souk\Actions;

use App\Domain\Caisse\Models\PaymentSlip;
use App\Domain\Caisse\Models\TaxDeclaration;
use App\Domain\Caisse\Services\NumberGenerator;
use App\Domain\Souk\Models\SoukContract;
use App\Domain\Souk\Services\SoukCalculator;
use App\Models\User;
use App\Support\PyMath;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Encaissement des redevances d'affermage — Flask souks.sou_payer.
 * Les mois se paient dans l'ordre chronologique (les N premiers mois dus) ; montant = somme des redevances de
 * chaque mois (période de renouvellement). Une déclaration « SOU-DECL… » + un bulletin « en attente ».
 * Écart : les mois déjà émis (en attente du régisseur) ne sont plus proposés, pour éviter un double bulletin ;
 * n° de BV généré si vide (Flask l'exigeait), toujours unique.
 */
class CollectSoukMonths
{
    public function __construct(protected SoukCalculator $calculator, protected NumberGenerator $numbers) {}

    /**
     * @param  list<string>  $months  mois « AAAA-MM »
     */
    public function handle(SoukContract $contract, array $months, User $agent, array $options = []): TaxDeclaration
    {
        $months = array_values(array_unique($months));
        sort($months);
        if (! $months) {
            throw ValidationException::withMessages(['months' => 'Veuillez sélectionner au moins un mois.']);
        }

        return DB::transaction(function () use ($contract, $months, $agent, $options) {
            SoukContract::whereKey($contract->id)->lockForUpdate()->first();
            $today = CarbonImmutable::today();
            $available = array_values(array_filter($this->calculator->unpaidMonths($contract, $today), fn ($m) => ! $m['est_emis']));
            $expected = array_column(array_slice($available, 0, count($months)), 'mois');
            if ($months !== $expected) {
                throw ValidationException::withMessages(['months' => 'Les mois se paient dans l’ordre chronologique. Commencez par : '.($available[0]['mois'] ?? '—').'.']);
            }
            $fees = array_column($available, 'redevance', 'mois');

            $slipNumber = trim((string) ($options['slip_number'] ?? ''));
            if ($slipNumber === '') {
                $slipNumber = $this->numbers->next('payment_slips', 'slip_number', 'BUL');
            } elseif (PaymentSlip::where('slip_number', $slipNumber)->exists()) {
                throw ValidationException::withMessages(['slip_number' => "Le bulletin n° {$slipNumber} existe déjà. Utilisez un autre numéro."]);
            }

            $total = PyMath::round(array_sum(array_map(fn ($m) => $fees[$m], $months)), 2);
            $paidOn = $today->toDateString();
            $notes = trim((string) ($options['notes'] ?? ''));
            $monthList = implode(', ', $months);

            $declaration = TaxDeclaration::create([
                'number' => $this->numbers->next('tax_declarations', 'number', 'SOU-DECL'),
                'module' => SoukCalculator::MODULE, 'taxable_type' => 'souk_contract', 'taxable_id' => $contract->id,
                'taxpayer_id' => $contract->taxpayer_id, 'municipality_id' => $contract->municipality_id,
                'year' => (int) substr(end($months), 0, 4), 'quarter' => 0,
                'base_amount' => SoukCalculator::monthlyFee($contract), 'rate' => 0, 'principal' => $total, 'total' => $total,
                'status' => 'emis', 'declared_on' => $paidOn, 'due_on' => $paidOn, 'agent_id' => $agent->id,
                'notes' => $monthList.($notes !== '' ? " | {$notes}" : ''),
            ]);
            foreach ($months as $m) {
                $declaration->periods()->create(['period' => $m, 'amount' => $fees[$m]]);
            }
            PaymentSlip::create([
                'slip_number' => $slipNumber, 'tax_declaration_id' => $declaration->id,
                'taxpayer_id' => $contract->taxpayer_id, 'municipality_id' => $contract->municipality_id,
                'amount' => $total, 'payment_mode' => $options['payment_mode'] ?? 'especes', 'paid_on' => $paidOn,
                'status' => 'en_attente', 'agent_id' => $agent->id,
                'transfer_number' => trim((string) ($options['transfer_number'] ?? '')) ?: null,
                'notes' => "Souk {$contract->souk_name} — ".count($months)." mois: {$monthList}",
            ]);
            activity('pieces')->causedBy($agent)->performedOn($declaration)
                ->withProperties(['contrat' => $contract->number, 'mois' => $months, 'montant' => $total, 'bulletin' => $slipNumber])
                ->log("Émission redevances souk contrat {$contract->number} — ".count($months).' mois');

            return $declaration;
        });
    }
}
