<?php

namespace App\Domain\Regie\Services;

use App\Domain\Regie\Models\CashClosure;
use App\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Comptabilité de la régie : toutes les recettes encaissées, quelle que soit leur origine
 *   - bulletins quittancés des modules (payment_slips « paye »), datés de la quittance ;
 *   - recettes saisies et validées à la régie (cash_entries « valide ») ;
 * et les sorties de caisse (bordereaux de versement au comptable).
 *
 * Les recettes payées par virement du Ministère (mode « virement_ministere ») ne passent pas par la caisse :
 * elles sont exclues du livre de caisse et de l'état mensuel (Modèle 10).
 */
class Ledger
{
    public const MODES = [
        'especes' => 'Espèces', 'cheque' => 'Chèque', 'virement' => 'Virement', 'versement' => 'Versement bancaire',
        'bulletin_manuel' => 'Bulletin manuel', 'virement_ministere' => 'Virement Ministère',
    ];

    /** Modes saisissables à la régie. */
    public const ENTRY_MODES = ['especes', 'cheque', 'virement', 'virement_ministere'];

    public const MINISTRY = 'virement_ministere';

    public const FAMILIES = [
        'fiscalite' => 'Taxes locales', 'domaine' => 'Produits du domaine', 'services' => 'Rémunérations de services', 'autres' => 'Autres recettes',
    ];

    public const MONTHS = [1 => 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];

    public static function modeLabel(?string $mode): string
    {
        return self::MODES[$mode] ?? ucfirst((string) $mode);
    }

    public static function familyLabel(?string $family): string
    {
        return self::FAMILIES[$family ?: 'autres'] ?? ucfirst((string) $family);
    }

    /** Solde de caisse au démarrage de l'application (Paramètres de la régie). */
    public static function opening(): array
    {
        return ['amount' => (float) Setting::get('regie.solde_initial', 0), 'date' => (string) Setting::get('regie.solde_initial_date', '2000-01-01')];
    }

    /**
     * Recettes (une ligne par pièce) entre deux dates incluses.
     *
     * @return Collection<int, object{date: string, source: string, id: int, ref: ?string, rubric_id: ?int, code: ?string, label: ?string, family: string, mode: string, amount: float, payer: ?string}>
     */
    public function receipts(string $from, string $to, bool $withMinistry = false): Collection
    {
        $slipDate = 'COALESCE(s.receipt_date, s.collected_on, s.paid_on)';
        $slips = DB::table('payment_slips as s')
            ->join('tax_declarations as d', 'd.id', '=', 's.tax_declaration_id')
            ->leftJoin('rubrics as r', 'r.module', '=', 'd.module')
            ->leftJoin('taxpayers as t', 't.id', '=', 's.taxpayer_id')
            ->where('s.status', 'paye')->whereNull('s.cancelled_at')
            ->whereRaw("$slipDate between ? and ?", [$from, $to])
            ->selectRaw("$slipDate as date, 'bv' as source, s.id, s.receipt_number as ref, r.id as rubric_id, r.code, r.label, COALESCE(r.family, 'autres') as family,
                s.payment_mode as mode, s.amount, TRIM(CONCAT(COALESCE(t.last_name, ''), ' ', COALESCE(t.first_name, ''))) as payer, d.module")
            ->get();

        $entries = DB::table('cash_entries as e')->join('rubrics as r', 'r.id', '=', 'e.rubric_id')
            ->where('e.status', 'valide')->whereNull('e.cancelled_at')->whereBetween('e.entry_date', [$from, $to])
            ->selectRaw("e.entry_date as date, 'saisie' as source, e.id, COALESCE(e.reference, e.number) as ref, r.id as rubric_id, r.code, r.label,
                COALESCE(r.family, 'autres') as family, e.payment_mode as mode, e.amount, e.payer, r.module")
            ->get();

        return $slips->concat($entries)
            ->map(function ($r) {
                $r->date = substr((string) $r->date, 0, 10);
                $r->amount = (float) $r->amount;
                $r->mode = $r->mode ?: 'especes';

                return $r;
            })
            ->when(! $withMinistry, fn ($c) => $c->where('mode', '!=', self::MINISTRY))
            ->sortBy([['date', 'asc'], ['source', 'asc'], ['id', 'asc']])->values();
    }

    /** Sorties de caisse : bordereaux de versement au comptable. */
    public function remittances(string $from, string $to): Collection
    {
        return DB::table('cash_remittances')->whereNull('cancelled_at')->whereBetween('remitted_on', [$from, $to])
            ->orderBy('remitted_on')->orderBy('id')->get()
            ->map(fn ($r) => (object) ['date' => substr((string) $r->remitted_on, 0, 10), 'id' => $r->id, 'number' => $r->number,
                'amount' => (float) $r->amount, 'mode' => $r->payment_mode, 'status' => $r->status, 'accountant' => $r->accountant]);
    }

    /** Solde de caisse à la fin de la journée $date (incluse). */
    public function balanceAt(string $date): float
    {
        $o = self::opening();
        if ($date < $o['date']) {
            return 0.0;
        }
        $in = $this->receipts($o['date'], $date)->sum('amount');
        $out = DB::table('cash_remittances')->whereNull('cancelled_at')->whereBetween('remitted_on', [$o['date'], $date])->sum('amount');

        return round($o['amount'] + $in - (float) $out, 2);
    }

    /**
     * Livre de caisse (Modèle 6) d'un mois : report du mois précédent, puis une ligne par jour
     * (recettes en espèces, autres modes, versements, solde).
     */
    public function cashBook(int $year, int $month): array
    {
        $start = CarbonImmutable::create($year, $month, 1);
        $end = $start->endOfMonth();
        $carried = $this->balanceAt($start->subDay()->toDateString());
        $receipts = $this->receipts($start->toDateString(), $end->toDateString())->groupBy('date');
        $remits = $this->remittances($start->toDateString(), $end->toDateString())->groupBy('date');

        $days = [];
        $balance = $carried;
        $dates = $receipts->keys()->merge($remits->keys())->unique()->sort()->values();
        foreach ($dates as $d) {
            $r = $receipts->get($d, collect());
            $cash = round($r->where('mode', 'especes')->sum('amount'), 2);
            $other = round($r->where('mode', '!=', 'especes')->sum('amount'), 2);
            $out = round($remits->get($d, collect())->sum('amount'), 2);
            $balance = round($balance + $cash + $other - $out, 2);
            $days[] = ['date' => $d, 'pieces' => $r->count(), 'cash' => $cash, 'other' => $other, 'receipts' => round($cash + $other, 2),
                'remittances' => $out, 'remittance_refs' => $remits->get($d, collect())->pluck('number')->all(), 'balance' => $balance];
        }

        return [
            'carried' => $carried, 'days' => $days,
            'total_receipts' => round(array_sum(array_column($days, 'receipts')), 2),
            'total_cash' => round(array_sum(array_column($days, 'cash')), 2),
            'total_other' => round(array_sum(array_column($days, 'other')), 2),
            'total_remittances' => round(array_sum(array_column($days, 'remittances')), 2),
            'closing' => $balance,
        ];
    }

    /**
     * État mensuel des recettes (Modèle 10) : par rubrique, report des mois antérieurs de l'année,
     * recettes du mois, cumul. Un mois clôturé est servi depuis son instantané figé.
     */
    public function monthlyState(int $year, int $month, bool $live = false): array
    {
        if (! $live && ($closure = MonthLock::closure($year, $month)) && $closure->snapshot) {
            return $closure->snapshot + ['closed' => true, 'closed_at' => $closure->closed_at?->toDateTimeString()];
        }
        $start = CarbonImmutable::create($year, $month, 1);
        $before = $month > 1 ? $this->receipts("$year-01-01", $start->subDay()->toDateString()) : collect();
        $current = $this->receipts($start->toDateString(), $start->endOfMonth()->toDateString());

        $rubrics = DB::table('rubrics')->orderBy('code')->get(['id', 'code', 'label', 'label_ar', 'family'])->keyBy('id');
        $rows = [];
        foreach ($rubrics as $id => $r) {
            $prev = round($before->where('rubric_id', $id)->sum('amount'), 2);
            $now = round($current->where('rubric_id', $id)->sum('amount'), 2);
            if ($prev == 0 && $now == 0) {
                continue;
            }
            $rows[] = ['rubric_id' => $id, 'code' => $r->code, 'label' => $r->label, 'label_ar' => $r->label_ar, 'family' => $r->family ?: 'autres',
                'previous' => $prev, 'month' => $now, 'cumulative' => round($prev + $now, 2),
                'pieces' => $current->where('rubric_id', $id)->count()];
        }
        $orphans = round($current->whereNull('rubric_id')->sum('amount'), 2);
        if ($orphans != 0) {
            $rows[] = ['rubric_id' => null, 'code' => '—', 'label' => 'Recettes sans rubrique', 'label_ar' => null, 'family' => 'autres',
                'previous' => round($before->whereNull('rubric_id')->sum('amount'), 2), 'month' => $orphans,
                'cumulative' => round($before->whereNull('rubric_id')->sum('amount') + $orphans, 2), 'pieces' => $current->whereNull('rubric_id')->count()];
        }

        return [
            'year' => $year, 'month' => $month, 'rows' => $rows,
            'total_previous' => round(array_sum(array_column($rows, 'previous')), 2),
            'total_month' => round(array_sum(array_column($rows, 'month')), 2),
            'total_cumulative' => round(array_sum(array_column($rows, 'cumulative')), 2),
            'ministry_excluded' => round($this->receipts($start->toDateString(), $start->endOfMonth()->toDateString(), true)->where('mode', self::MINISTRY)->sum('amount'), 2),
            'closed' => false,
        ];
    }

    /** Situation journalière : recettes du jour par rubrique et par mode, versements, soldes. */
    public function dailySituation(string $date): array
    {
        $r = $this->receipts($date, $date);
        $byRubric = $r->groupBy(fn ($x) => $x->rubric_id ?? 0)->map(fn ($g) => [
            'code' => $g->first()->code ?? '—', 'label' => $g->first()->label ?? 'Sans rubrique', 'pieces' => $g->count(),
            'modes' => $g->groupBy('mode')->map(fn ($m) => round($m->sum('amount'), 2))->all(), 'total' => round($g->sum('amount'), 2),
        ])->sortBy('code')->values()->all();
        $remits = $this->remittances($date, $date);
        $opening = $this->balanceAt(CarbonImmutable::parse($date)->subDay()->toDateString());

        return [
            'date' => $date, 'opening' => $opening, 'rubrics' => $byRubric,
            'modes' => $r->groupBy('mode')->map(fn ($m) => round($m->sum('amount'), 2))->all(),
            'receipts' => round($r->sum('amount'), 2), 'pieces' => $r->count(),
            'remittances' => $remits->all(), 'total_remittances' => round($remits->sum('amount'), 2),
            'closing' => round($opening + $r->sum('amount') - $remits->sum('amount'), 2),
            'ministry' => DB::table('cash_entries')->where('status', 'valide')->whereNull('cancelled_at')->where('entry_date', $date)
                ->where('payment_mode', self::MINISTRY)->sum('amount'),
        ];
    }

    /** Opérations avec le Trésor (Modèle 7) : par mois, recettes, versements, versements accusés, solde. */
    public function treasuryOperations(int $year): array
    {
        $months = [];
        foreach (range(1, 12) as $m) {
            $start = CarbonImmutable::create($year, $m, 1);
            if ($start->isFuture()) {
                break;
            }
            $end = $start->endOfMonth()->toDateString();
            $remits = $this->remittances($start->toDateString(), $end);
            $months[] = [
                'month' => $m, 'opening' => $this->balanceAt($start->subDay()->toDateString()),
                'receipts' => round($this->receipts($start->toDateString(), $end)->sum('amount'), 2),
                'remittances' => round($remits->sum('amount'), 2),
                'acknowledged' => round($remits->where('status', 'accuse')->sum('amount'), 2),
                'pending' => $remits->where('status', '!=', 'accuse')->count(),
                'closing' => $this->balanceAt($end),
                'items' => $remits->all(),
            ];
        }

        return ['year' => $year, 'months' => $months,
            'total_receipts' => round(array_sum(array_column($months, 'receipts')), 2),
            'total_remittances' => round(array_sum(array_column($months, 'remittances')), 2),
            'total_acknowledged' => round(array_sum(array_column($months, 'acknowledged')), 2)];
    }

    /** Récapitulatif comptable d'une période : par famille, rubrique et mode de paiement. */
    public function recap(string $from, string $to): array
    {
        $r = $this->receipts($from, $to, true);
        $modes = $r->pluck('mode')->unique()->sort()->values()->all();
        $families = $r->groupBy('family')->map(fn ($f, $key) => [
            'family' => $key, 'label' => self::familyLabel($key),
            'rubrics' => $f->groupBy(fn ($x) => $x->rubric_id ?? 0)->map(fn ($g) => [
                'code' => $g->first()->code ?? '—', 'label' => $g->first()->label ?? 'Sans rubrique', 'pieces' => $g->count(),
                'modes' => $g->groupBy('mode')->map(fn ($m) => round($m->sum('amount'), 2))->all(), 'total' => round($g->sum('amount'), 2),
            ])->sortBy('code')->values()->all(),
            'modes' => $f->groupBy('mode')->map(fn ($m) => round($m->sum('amount'), 2))->all(),
            'total' => round($f->sum('amount'), 2),
        ])->sortKeys()->values()->all();

        return ['from' => $from, 'to' => $to, 'modes' => $modes, 'families' => $families,
            'totals' => $r->groupBy('mode')->map(fn ($m) => round($m->sum('amount'), 2))->all(), 'total' => round($r->sum('amount'), 2), 'pieces' => $r->count()];
    }

    /** Tableau des recettes d'une année : rubriques × mois. */
    public function yearTable(int $year): array
    {
        $r = $this->receipts("$year-01-01", "$year-12-31", true);
        $rows = $r->groupBy(fn ($x) => $x->rubric_id ?? 0)->map(function ($g) {
            $months = array_fill(1, 12, 0.0);
            foreach ($g as $x) {
                $months[(int) substr($x->date, 5, 2)] += $x->amount;
            }

            return ['code' => $g->first()->code ?? '—', 'label' => $g->first()->label ?? 'Sans rubrique', 'family' => $g->first()->family,
                'months' => array_map(fn ($v) => round($v, 2), $months), 'total' => round($g->sum('amount'), 2)];
        })->sortBy('code')->values()->all();
        $totals = array_fill(1, 12, 0.0);
        foreach ($rows as $row) {
            foreach ($row['months'] as $m => $v) {
                $totals[$m] = round($totals[$m] + $v, 2);
            }
        }

        return ['year' => $year, 'rows' => $rows, 'totals' => $totals, 'total' => round(array_sum($totals), 2)];
    }
}
