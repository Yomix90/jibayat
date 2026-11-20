<?php

namespace App\Domain\Regie\Services;

use App\Domain\Caisse\Services\NumberGenerator;
use App\Domain\Documents\Models\StampValue;
use App\Domain\Documents\Services\StampService;
use App\Domain\Regie\Models\CashEntry;
use App\Domain\Regie\Models\StockMovement;
use App\Domain\Regie\Models\TicketDischarge;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Stock des tickets et vignettes (en unités) :
 *   réserve TGR  = dotations + ajustements TGR − tout ce que la régie a reçu (paquets reçus et retraits) ;
 *   stock régie  = reçu + ajustements régie − carnets remis aux agents − tickets remis par décharge (+ retours) ;
 *   chez les agents = carnets affectés non versés + tickets remis non soldés.
 * Les carnets (vignettes) suivent le circuit existant paquets ➜ carnets ➜ versements ; les tickets (souk…)
 * sortent par bon de décharge, soldé au retour (vendus = remis − rendus) : la recette est alors enregistrée.
 */
class StockService
{
    public const MOVEMENTS = [
        'dotation' => 'Dotation de la réserve TGR', 'retrait' => 'Retrait TGR ➜ régie',
        'ajustement_tgr' => 'Ajustement de la réserve TGR', 'ajustement_regie' => 'Ajustement du stock de la régie',
    ];

    public function __construct(protected NumberGenerator $numbers) {}

    protected static function bookUnits(): string
    {
        return 'COALESCE(b.last_number - b.first_number + 1, v.units_per_book)';
    }

    /** @return array<int, array{id: int, label: string, type: string, unit_value: float, units_per_book: int, rubric_id: ?int, threshold: int, tgr: int, received: int, regie: int, with_agents: int, consumed: int}> */
    public function state(?int $year = null): array
    {
        $values = StampValue::where('is_active', true)->orderBy('type')->orderBy('label')->get();
        $mv = StockMovement::active()->when($year, fn ($q) => $q->where('year', $year))
            ->selectRaw('stamp_value_id, movement, SUM(quantity) q')->groupBy('stamp_value_id', 'movement')->get()
            ->groupBy('stamp_value_id')->map(fn ($g) => $g->pluck('q', 'movement')->map(fn ($v) => (int) $v));
        $packs = DB::table('stamp_packs')->when($year, fn ($q) => $q->whereYear('received_on', $year))
            ->selectRaw('stamp_value_id, SUM(COALESCE(quantity, 0)) q')->groupBy('stamp_value_id')->pluck('q', 'stamp_value_id');
        $books = DB::table('stamp_books as b')->join('stamp_values as v', 'v.id', '=', 'b.stamp_value_id')
            ->selectRaw('b.stamp_value_id, b.status, SUM('.self::bookUnits().') q')->groupBy('b.stamp_value_id', 'b.status')->get()
            ->groupBy('stamp_value_id')->map(fn ($g) => $g->pluck('q', 'status')->map(fn ($v) => (int) $v));
        $tickets = TicketDischarge::active()->selectRaw('stamp_value_id, status, SUM(quantity) q, SUM(returned) r')->groupBy('stamp_value_id', 'status')->get()
            ->groupBy('stamp_value_id');

        $out = [];
        foreach ($values as $v) {
            $m = $mv->get($v->id, collect());
            $received = (int) ($packs[$v->id] ?? 0) + (int) $m->get('retrait', 0);
            $b = $books->get($v->id, collect());
            $t = $tickets->get($v->id, collect());
            $ticketsOut = (int) $t->sum('q') - (int) $t->sum('r');
            $ticketsOpen = (int) $t->where('status', 'remis')->sum('q') - (int) $t->where('status', 'remis')->sum('r');
            $out[$v->id] = [
                'id' => $v->id, 'label' => $v->label, 'type' => $v->type, 'unit_value' => (float) $v->unit_value, 'units_per_book' => (int) $v->units_per_book,
                'rubric_id' => $v->rubric_id, 'threshold' => (int) $v->alert_threshold,
                'tgr' => (int) $m->get('dotation', 0) + (int) $m->get('ajustement_tgr', 0) - $received,
                'received' => $received,
                'regie' => $received + (int) $m->get('ajustement_regie', 0) - (int) $b->get('affecte', 0) - (int) $b->get('consomme', 0) - $ticketsOut,
                'with_agents' => (int) $b->get('affecte', 0) + $ticketsOpen,
                'consumed' => (int) $b->get('consomme', 0) + ($ticketsOut - $ticketsOpen),
            ];
        }

        return $out;
    }

    public function move(int $valueId, string $movement, int $quantity, string $on, ?string $reference, ?string $notes, User $by): StockMovement
    {
        if (! isset(self::MOVEMENTS[$movement])) {
            throw ValidationException::withMessages(['movement' => 'Mouvement inconnu.']);
        }
        if ($quantity === 0 || ($quantity < 0 && ! str_starts_with($movement, 'ajustement'))) {
            throw ValidationException::withMessages(['quantity' => 'Quantité invalide.']);
        }
        if ($movement === 'retrait') {
            $tgr = $this->state()[$valueId]['tgr'] ?? 0;
            if ($quantity > $tgr) {
                throw ValidationException::withMessages(['quantity' => "La réserve TGR ne contient que $tgr unité(s)."]);
            }
        }
        $m = StockMovement::create(['stamp_value_id' => $valueId, 'movement' => $movement, 'quantity' => $quantity, 'moved_on' => $on,
            'year' => (int) substr($on, 0, 4), 'reference' => $reference ?: null, 'notes' => $notes ?: null, 'created_by' => $by->id]);
        activity('regie')->causedBy($by)->performedOn($m)->withProperties(['quantite' => $quantity])->log(self::MOVEMENTS[$movement]);

        return $m;
    }

    /** Bon de décharge : tickets remis à un agent. */
    public function discharge(array $data, User $by): TicketDischarge
    {
        $value = StampValue::findOrFail($data['stamp_value_id']);
        $qty = StampService::quantity($data['first_number'] ?? null, $data['last_number'] ?? null) ?: (int) ($data['quantity'] ?? 0);
        if ($qty <= 0) {
            throw ValidationException::withMessages(['quantity' => 'Indiquez la quantité ou les numéros (du … au …).']);
        }
        $stock = $this->state()[$value->id]['regie'] ?? 0;
        if ($qty > $stock) {
            throw ValidationException::withMessages(['quantity' => "Le stock de la régie ne contient que $stock unité(s)."]);
        }

        return DB::transaction(function () use ($data, $value, $qty, $by) {
            $d = TicketDischarge::create([
                'number' => $this->numbers->next('ticket_discharges', 'number', 'DCH', (int) substr($data['discharge_date'], 0, 4)),
                'discharge_date' => $data['discharge_date'], 'stamp_value_id' => $value->id, 'employee_id' => $data['employee_id'] ?: null,
                'place' => $data['place'] ?: null, 'first_number' => $data['first_number'] ?: null, 'last_number' => $data['last_number'] ?: null,
                'quantity' => $qty, 'notes' => $data['notes'] ?? null, 'created_by' => $by->id,
            ]);
            activity('regie')->causedBy($by)->performedOn($d)->withProperties(['quantite' => $qty])->log('Décharge de tickets');

            return $d;
        });
    }

    /** Retour de l'agent : tickets rendus, vendus = remis − rendus ; la recette est enregistrée (validée) à la date du retour. */
    public function settle(TicketDischarge $d, int $returned, string $on, User $by): void
    {
        if ($d->status !== 'remis' || $d->isCancelled()) {
            throw ValidationException::withMessages(['returned' => 'Cette décharge est déjà soldée.']);
        }
        if ($returned < 0 || $returned > $d->quantity) {
            throw ValidationException::withMessages(['returned' => 'Le nombre de tickets rendus dépasse le nombre remis.']);
        }
        MonthLock::assertOpen($on, 'returned');
        DB::transaction(function () use ($d, $returned, $on, $by) {
            $d->returned = $returned;
            $entry = $this->recordReceipt($d, $on, $by);
            $d->update(['returned' => $returned, 'status' => 'solde', 'settled_on' => $on, 'amount_paid' => $entry?->amount ?? 0, 'cash_entry_id' => $entry?->id]);
            activity('regie')->causedBy($by)->performedOn($d)->withProperties(['vendus' => $d->sold(), 'montant' => $entry?->amount])->log('Décharge soldée');
        });
    }

    protected function recordReceipt(TicketDischarge $d, string $on, User $by): ?CashEntry
    {
        $value = $d->value;
        $sold = $d->sold();
        if ($sold === 0) {
            return null;
        }
        if (! $value->rubric_id) {
            throw ValidationException::withMessages(['returned' => "Associez une rubrique à « {$value->label} » (Régie ➜ Configuration du stock) pour enregistrer la recette."]);
        }
        $entry = app(EntryService::class)->createDrafts($on, [[
            'rubric_id' => $value->rubric_id, 'payment_mode' => 'especes', 'quantity' => $sold, 'unit_price' => $value->unit_value,
            'reference' => $d->number, 'payer' => $d->employee ? trim($d->employee->last_name.' '.$d->employee->first_name) : null,
            'description' => "Tickets vendus ($sold) — décharge {$d->number}".($d->place ? " — {$d->place}" : ''),
        ]], $by, 'decharge')[0];
        $entry->update(['status' => 'valide', 'validated_by' => $by->id, 'validated_at' => now()]);

        return $entry;
    }

    /** Modifier une décharge (correction de la sortie de tickets) : historique motivé ; une décharge soldée voit sa recette refaite. */
    public function correct(TicketDischarge $d, array $data, string $reason, User $by): void
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Le motif de la modification est obligatoire.']);
        }
        if ($d->isCancelled()) {
            throw ValidationException::withMessages(['reason' => 'Décharge annulée.']);
        }
        $qty = StampService::quantity($data['first_number'] ?? null, $data['last_number'] ?? null) ?: (int) ($data['quantity'] ?? 0);
        $returned = (int) ($data['returned'] ?? $d->returned);
        if ($qty <= 0 || $returned < 0 || $returned > $qty) {
            throw ValidationException::withMessages(['quantity' => 'Quantités incohérentes.']);
        }
        $available = ($this->state()[$d->stamp_value_id]['regie'] ?? 0) + ($d->quantity - $d->returned);
        if ($qty - $returned > $available) {
            throw ValidationException::withMessages(['quantity' => "Le stock de la régie ne permet pas cette sortie ($available unité(s) disponibles)."]);
        }
        DB::transaction(function () use ($d, $data, $qty, $returned, $reason, $by) {
            $before = ['quantite' => $d->quantity, 'rendus' => $d->returned, 'du' => $d->first_number, 'au' => $d->last_number];
            $history = $d->corrections ?? [];
            $history[] = ['le' => now()->toDateTimeString(), 'par' => $by->name, 'motif' => trim($reason), 'avant' => $before,
                'apres' => ['quantite' => $qty, 'rendus' => $returned, 'du' => $data['first_number'] ?? null, 'au' => $data['last_number'] ?? null]];
            $d->fill(['quantity' => $qty, 'returned' => $returned, 'first_number' => ($data['first_number'] ?? '') ?: null,
                'last_number' => ($data['last_number'] ?? '') ?: null, 'corrections' => $history]);
            if ($d->status === 'solde') {
                $old = $d->cash_entry_id ? CashEntry::find($d->cash_entry_id) : null;
                $date = $d->settled_on->toDateString();
                MonthLock::assertOpen($date, 'reason');
                if ($old && ! $old->isCancelled()) {
                    $old->cancel($by, 'Correction de la décharge '.$d->number.' : '.trim($reason));
                }
                $entry = $this->recordReceipt($d, $date, $by);
                $d->fill(['cash_entry_id' => $entry?->id, 'amount_paid' => $entry?->amount ?? 0]);
            }
            $d->save();
            activity('regie')->causedBy($by)->performedOn($d)->withProperties(['motif' => $reason])->log('Décharge modifiée');
        });
    }

    /**
     * Intelligence du stock : sorties mensuelles des 12 derniers mois, consommation moyenne, couverture
     * (mois) du stock de la régie et de la réserve TGR, commande conseillée (3 mois de consommation).
     */
    public function intelligence(?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $from = $today->startOfMonth()->subMonths(11);
        $months = [];
        for ($i = 0; $i < 12; $i++) {
            $months[] = $from->addMonths($i)->format('Y-m');
        }
        $books = DB::table('stamp_books as b')->join('stamp_values as v', 'v.id', '=', 'b.stamp_value_id')
            ->whereIn('b.status', ['affecte', 'consomme'])->where('b.assigned_on', '>=', $from->toDateString())
            ->selectRaw("b.stamp_value_id, DATE_FORMAT(b.assigned_on, '%Y-%m') m, SUM(".self::bookUnits().') q')->groupBy('b.stamp_value_id', 'm')->get();
        $tickets = TicketDischarge::active()->where('discharge_date', '>=', $from->toDateString())
            ->selectRaw("stamp_value_id, DATE_FORMAT(discharge_date, '%Y-%m') m, SUM(quantity - returned) q")->groupBy('stamp_value_id', 'm')->get();
        $flows = $books->concat($tickets)->groupBy('stamp_value_id');

        $rows = [];
        foreach ($this->state() as $id => $v) {
            $series = array_fill_keys($months, 0);
            foreach ($flows->get($id, collect()) as $f) {
                if (isset($series[$f->m])) {
                    $series[$f->m] += (int) $f->q;
                }
            }
            // Moyenne sur les mois écoulés depuis la première sortie (un article récent n'est pas dilué sur 12 mois).
            $active = array_filter($series);
            $avg = $active ? round(array_sum($series) / (12 - array_search(array_key_first($active), $months, true)), 1) : 0.0;
            $cover = $avg > 0 ? round($v['regie'] / $avg, 1) : null;
            $tgrCover = $avg > 0 ? round($v['tgr'] / $avg, 1) : null;
            $order = $avg > 0 ? max(0, (int) ceil(3 * $avg - $v['regie'])) : 0;
            $level = $v['regie'] <= 0 ? 'rupture' : (($cover !== null && $cover < 1) || ($v['threshold'] && $v['regie'] < $v['threshold']) ? 'bas' : 'ok');
            $rows[] = $v + ['series' => $series, 'average' => $avg, 'cover' => $cover, 'tgr_cover' => $tgrCover, 'order' => $order,
                'order_value' => round($order * $v['unit_value'], 2), 'level' => $level];
        }

        return ['months' => $months, 'rows' => $rows];
    }
}
