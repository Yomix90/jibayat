<?php

namespace App\Domain\Regie\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Contrôle de la régie : centre de contrôle (tout ce qui reste à corriger), contrôle croisé des saisies
 * d'un mois et état de rapprochement régie / comptable (Modèle 10 ✕ relevé TGR).
 */
class ControlService
{
    public function __construct(protected Ledger $ledger, protected MinutesService $minutes, protected StockService $stock) {}

    /**
     * @return list<array{level: string, title: string, detail: string, route: ?string, params?: array}>
     */
    public function issues(?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $out = [];
        $add = function (string $level, string $title, string $detail, ?string $route = null, array $params = []) use (&$out) {
            $out[] = compact('level', 'title', 'detail', 'route', 'params');
        };

        $drafts = DB::table('cash_entries')->where('status', 'brouillon')->whereNull('cancelled_at');
        if ($n = (clone $drafts)->count()) {
            $old = (clone $drafts)->where('entry_date', '<', $today->subDays(2)->toDateString())->count();
            $add($old ? 'danger' : 'warning', "$n recette(s) en brouillon", $old ? "$old saisie(s) de plus de 2 jours attendent la validation." : 'À valider par le régisseur.', 'regie.brouillons');
        }
        if ($n = DB::table('payment_slips')->where('status', 'en_attente')->whereNull('cancelled_at')->where('created_at', '<', $today->subDays(7))->count()) {
            $add('warning', "$n bulletin(s) en attente depuis plus de 7 jours", 'Quittancer, rejeter ou annuler.', 'caisse.bulletins');
        }
        $opening = Ledger::opening()['date'];
        for ($m = $today->startOfMonth()->subMonths(6); $m->lt($today->startOfMonth()); $m = $m->addMonth()) {
            if ($m->endOfMonth()->toDateString() >= $opening && ! MonthLock::closure($m->year, $m->month)) {
                $add('danger', sprintf('Mois de %s %d non clôturé', Ledger::MONTHS[$m->month], $m->year), 'Clôturez le mois pour figer l’état mensuel.', 'regie.cloture', ['annee' => $m->year]);
            }
        }
        $late = DB::table('cash_remittances')->where('status', 'verse')->whereNull('cancelled_at')->where('remitted_on', '<', $today->subDays(15)->toDateString())->count();
        if ($late) {
            $add('warning', "$late versement(s) non accusé(s) depuis plus de 15 jours", 'Récupérez l’accusé du comptable.', 'regie.versements');
        }
        $ceiling = (float) \App\Models\Setting::get('regie.plafond', 0);
        $balance = $this->ledger->balanceAt($today->toDateString());
        if ($ceiling > 0 && $balance > $ceiling) {
            $add('danger', 'Encaisse au-dessus du plafond', sprintf('Solde %s DH pour un plafond de %s DH : versez au comptable.', number_format($balance, 2, ',', ' '), number_format($ceiling, 2, ',', ' ')), 'regie.versements');
        }
        foreach ($this->minutes->missing($today) as $pv) {
            $add('warning', $pv.' manquant', 'Établissez le procès-verbal.', 'regie.pv-arrete');
        }
        if ($n = DB::table('ticket_discharges')->where('status', 'remis')->whereNull('cancelled_at')->where('discharge_date', '<', $today->subDays(7)->toDateString())->count()) {
            $add('warning', "$n décharge(s) de tickets non soldée(s) depuis plus de 7 jours", 'Enregistrez le retour des agents.', 'regie.saisie-tickets');
        }
        foreach ($this->stock->state() as $v) {
            if ($v['regie'] <= 0 && ($v['received'] > 0 || $v['tgr'] > 0)) {
                $add('danger', "Rupture : {$v['label']}", 'Stock de la régie épuisé : retirez à la réserve TGR.', 'regie.stock');
            } elseif ($v['threshold'] && $v['regie'] < $v['threshold']) {
                $add('warning', "Stock bas : {$v['label']}", "{$v['regie']} unité(s), seuil {$v['threshold']}.", 'regie.stock');
            }
        }
        if ($n = DB::table('printed_documents')->whereNull('scan_path')->where('first_printed_at', '<', $today->subDays(30))->count()) {
            $add('info', "$n pièce(s) imprimée(s) non scannée(s) depuis 30 jours", 'Joignez le scan au registre.', 'regie.controle-registre');
        }
        $last = $today->subMonth();
        $gap = $this->reconciliation($last->year, $last->month);
        if ($gap['statement'] && abs($gap['total_gap']) > 0.009) {
            $add('danger', sprintf('Écart régie / TGR en %s %d', Ledger::MONTHS[$last->month], $last->year), sprintf('%s DH à justifier.', number_format($gap['total_gap'], 2, ',', ' ')), 'regie.etat-rapprochement', ['annee' => $last->year, 'mois' => $last->month]);
        }

        $rank = ['danger' => 0, 'warning' => 1, 'info' => 2];
        usort($out, fn ($a, $b) => $rank[$a['level']] <=> $rank[$b['level']]);

        return $out;
    }

    /**
     * Contrôle croisé des saisies d'un mois : recettes des modules ✕ saisies de la régie par jour,
     * doublons de références, recettes sans référence, quittances partagées.
     */
    public function crossCheck(int $year, int $month): array
    {
        $start = CarbonImmutable::create($year, $month, 1);
        $r = $this->ledger->receipts($start->toDateString(), $start->endOfMonth()->toDateString(), true);
        $days = $r->groupBy('date')->map(fn ($g, $d) => ['date' => $d, 'bv' => round($g->where('source', 'bv')->sum('amount'), 2),
            'saisie' => round($g->where('source', 'saisie')->sum('amount'), 2), 'ministry' => round($g->where('mode', Ledger::MINISTRY)->sum('amount'), 2),
            'total' => round($g->sum('amount'), 2), 'pieces' => $g->count()])->sortKeys()->values()->all();
        $book = $this->ledger->cashBook($year, $month);

        $duplicates = DB::table('cash_entries')->whereNull('cancelled_at')->whereNotNull('reference')->whereYear('entry_date', $year)
            ->selectRaw('reference, COUNT(*) n, SUM(amount) total, GROUP_CONCAT(number) numbers')->groupBy('reference')->havingRaw('COUNT(*) > 1')->get()->all();
        $noRef = DB::table('cash_entries')->whereNull('cancelled_at')->whereNull('reference')->whereBetween('entry_date', [$start->toDateString(), $start->endOfMonth()->toDateString()])->count();
        $remitted = (float) DB::table('cash_remittances')->whereNull('cancelled_at')->where('period_from', '>=', $start->toDateString())->where('period_to', '<=', $start->endOfMonth()->toDateString())->sum('amount');

        return ['days' => $days, 'book' => $book, 'duplicates' => $duplicates, 'no_reference' => $noRef, 'remitted' => round($remitted, 2),
            'total' => round($r->where('mode', '!=', Ledger::MINISTRY)->sum('amount'), 2), 'checks' => [
                'Le total des jours égale le livre de caisse' => abs(array_sum(array_column($days, 'total')) - array_sum(array_column($days, 'ministry')) - $book['total_receipts']) < 0.01,
                'Aucune référence en double' => ! $duplicates,
                'Toutes les saisies ont une référence' => $noRef === 0,
                'Recettes du mois versées au comptable' => $remitted + 0.01 >= $book['total_receipts'],
            ]];
    }

    /** État de rapprochement régie / comptable : Modèle 10 du mois ✕ relevé TGR importé, par code budgétaire. */
    public function reconciliation(int $year, int $month): array
    {
        $state = $this->ledger->monthlyState($year, $month);
        $statement = DB::table('treasury_statements')->where('year', $year)->where('month', $month)->first();
        $tgr = $statement ? DB::table('treasury_statement_lines')->where('treasury_statement_id', $statement->id)
            ->selectRaw('budget_code, MAX(nature) nature, SUM(amount) amount')->groupBy('budget_code')->get()->keyBy('budget_code') : collect();
        $rows = [];
        foreach ($state['rows'] as $r) {
            $t = (float) ($tgr->get($r['code'])->amount ?? 0);
            $rows[$r['code']] = ['code' => $r['code'], 'label' => $r['label'], 'regie' => $r['month'], 'tgr' => round($t, 2), 'gap' => round($r['month'] - $t, 2)];
        }
        foreach ($tgr as $code => $t) {
            if (! isset($rows[$code])) {
                $rows[$code] = ['code' => $code, 'label' => $t->nature, 'regie' => 0.0, 'tgr' => round((float) $t->amount, 2), 'gap' => round(-(float) $t->amount, 2)];
            }
        }
        ksort($rows);
        $start = CarbonImmutable::create($year, $month, 1);
        $remitted = (float) DB::table('cash_remittances')->whereNull('cancelled_at')->whereBetween('remitted_on', [$start->toDateString(), $start->endOfMonth()->toDateString()])->sum('amount');

        return ['year' => $year, 'month' => $month, 'statement' => (bool) $statement, 'rows' => array_values($rows),
            'total_regie' => $state['total_month'], 'total_tgr' => round($tgr->sum('amount'), 2),
            'total_gap' => round($state['total_month'] - $tgr->sum('amount'), 2), 'remitted' => round($remitted, 2), 'closed' => $state['closed']];
    }
}
