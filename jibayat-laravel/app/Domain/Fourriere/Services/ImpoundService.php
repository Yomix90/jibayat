<?php

namespace App\Domain\Fourriere\Services;

use App\Domain\Caisse\Models\PaymentSlip;
use App\Domain\Caisse\Models\TaxDeclaration;
use App\Domain\Caisse\Services\NumberGenerator;
use App\Domain\Fourriere\Models\AuctionGroup;
use App\Domain\Fourriere\Models\AuctionSale;
use App\Domain\Fourriere\Models\ImpoundCase;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Fourrière — encaissement, sortie, enchères (Flask fou_payer, fou_sortie, fou_vente_valider).
 */
class ImpoundService
{
    public function __construct(protected NumberGenerator $numbers) {}

    /**
     * Encaissement des frais : déclaration « FOU-DECL… » (base = montant dû calculé) + bulletin « en attente ».
     * Écart : le montant est le montant dû calculé (Flask laissait l'agent le saisir librement) ; le n° de BV est généré
     * si vide. Un dossier déjà payé, sorti ou avec un bulletin en attente est refusé.
     */
    public function pay(ImpoundCase $c, User $agent, array $owner, string $slipNumber = ''): TaxDeclaration
    {
        return DB::transaction(function () use ($c, $agent, $owner, $slipNumber) {
            $c = ImpoundCase::whereKey($c->id)->lockForUpdate()->firstOrFail();
            if ($c->status === 'sortie' || ImpoundCalculator::hasValidatedPayment($c) || ImpoundCalculator::hasPendingPayment($c)) {
                throw ValidationException::withMessages(['slip_number' => 'Ce dossier est déjà payé ou a déjà un bulletin en attente.']);
            }
            $slipNumber = trim($slipNumber);
            if ($slipNumber === '') {
                $slipNumber = $this->numbers->next('payment_slips', 'slip_number', 'BUL');
            } elseif (PaymentSlip::where('slip_number', $slipNumber)->exists()) {
                throw ValidationException::withMessages(['slip_number' => "Le bulletin n° {$slipNumber} existe déjà."]);
            }
            $e = ImpoundCalculator::enrich($c);
            $today = CarbonImmutable::today()->toDateString();
            $c->update(['owner_name' => $owner['name'] ?? null, 'owner_cin' => $owner['cin'] ?? null, 'owner_phone' => $owner['phone'] ?? null,
                'slip_number' => $slipNumber, 'days' => $e['days'], 'payment_agent_id' => $agent->id]);
            $d = TaxDeclaration::create([
                'number' => $this->numbers->next('tax_declarations', 'number', 'FOU-DECL'),
                'module' => ImpoundCalculator::MODULE, 'taxable_type' => 'impound_case', 'taxable_id' => $c->id,
                'taxpayer_id' => $c->taxpayer_id, 'municipality_id' => $c->municipality_id, 'year' => (int) substr($today, 0, 4), 'quarter' => 0,
                'base_amount' => $e['amount'], 'rate' => (float) $c->daily_rate, 'principal' => $e['amount'], 'total' => $e['amount'],
                'status' => 'emis', 'declared_on' => $today, 'due_on' => $today, 'agent_id' => $agent->id,
                'notes' => "Fourrière {$c->number} — {$e['days']} jours",
            ]);
            PaymentSlip::create([
                'slip_number' => $slipNumber, 'tax_declaration_id' => $d->id, 'taxpayer_id' => $c->taxpayer_id, 'municipality_id' => $c->municipality_id,
                'amount' => $e['amount'], 'payment_mode' => 'bulletin_manuel', 'paid_on' => $today, 'status' => 'en_attente', 'agent_id' => $agent->id,
                'notes' => "FOU — {$c->number} — ".($owner['name'] ?? ''),
            ]);
            activity('pieces')->causedBy($agent)->performedOn($c)->withProperties(['montant' => $e['amount'], 'jours' => $e['days'], 'bulletin' => $slipNumber])
                ->log("Frais de fourrière dossier {$c->number} — {$e['days']} jours");

            return $d;
        });
    }

    /** Sortie : seulement si un paiement a été validé par le régisseur (Flask fou_sortie). */
    public function release(ImpoundCase $c, User $by): void
    {
        if (! ImpoundCalculator::hasValidatedPayment($c)) {
            throw ValidationException::withMessages(['release' => 'Impossible : aucun paiement validé par le régisseur.']);
        }
        $today = CarbonImmutable::today()->toDateString();
        $c->update(['status' => 'sortie', 'release_validated_on' => $today, 'released_on' => $today]);
        activity('pieces')->causedBy($by)->performedOn($c)->log("Sortie validée — véhicule {$c->registration} restitué (dossier {$c->number})");
    }

    public function createGroup(array $data, User $by): AuctionGroup
    {
        $n = AuctionGroup::count() + 1;
        do {
            $number = sprintf('GE%d%04d', now()->year, $n++);
        } while (AuctionGroup::where('number', $number)->exists());

        return AuctionGroup::create($data + ['number' => $number, 'status' => 'ouvert', 'agent_id' => $by->id]);
    }

    /** Vente aux enchères : vente enregistrée, véhicule sorti ; le groupe est « vendu » quand tous ses véhicules le sont. */
    public function sell(AuctionGroup $g, ImpoundCase $c, array $data, User $by): AuctionSale
    {
        return DB::transaction(function () use ($g, $c, $data, $by) {
            if (AuctionSale::where('impound_case_id', $c->id)->exists()) {
                throw ValidationException::withMessages(['sale' => 'Ce véhicule est déjà vendu.']);
            }
            $sale = AuctionSale::create($data + ['auction_group_id' => $g->id, 'impound_case_id' => $c->id, 'sold_on' => today(), 'agent_id' => $by->id]);
            $c->update(['status' => 'sortie', 'release_validated_on' => today()]);
            if (AuctionSale::where('auction_group_id', $g->id)->count() >= $g->vehicles()->count()) {
                $g->update(['status' => 'vendu']);
            }
            activity('pieces')->causedBy($by)->performedOn($c)->withProperties(['prix' => $data['hammer_price'] ?? 0, 'acheteur' => $data['buyer_name'] ?? null])
                ->log("Vente aux enchères du véhicule {$c->registration} (groupe {$g->number})");

            return $sale;
        });
    }
}
