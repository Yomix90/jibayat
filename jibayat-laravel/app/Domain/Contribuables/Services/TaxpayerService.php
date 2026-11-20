<?php

namespace App\Domain\Contribuables\Services;

use App\Domain\Caisse\Services\NumberGenerator;
use App\Models\Municipality;
use App\Models\Taxpayer;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Répertoire des contribuables — portage de Flask modules/contribuables.py.
 * Impayé = déclarations ni payées ni annulées et sans bulletin validé ; modules = dossiers du redevable dans chaque taxe.
 */
class TaxpayerService
{
    /** Code module ➜ [table, libellé court] (Flask MODULES). */
    public const MODULES = [
        'TNB' => ['parcels', 'TNB'],
        'DEBITS_BOISSONS' => ['beverage_outlets', 'TDB'],
        'STATIONNEMENT' => ['vehicles', 'Stat.'],
        'FOURRIERE' => ['impound_cases', 'Fourrière'],
        'OCCUPATION_DOMAINE' => ['public_occupations', 'ODP'],
        'LOCATION_LOCAUX' => ['leases', 'Location'],
        'AFFERMAGE_SOUKS' => ['souk_contracts', 'Souks'],
    ];

    public const PAID_SLIP = ['valide', 'encaisse', 'paye'];

    /** Déclarations impayées (une ligne par déclaration). */
    public function unpaidQuery(): Builder
    {
        return DB::table('tax_declarations as d')
            ->whereNotIn('d.status', ['annule', 'paye'])->whereNull('d.cancelled_at')
            ->whereNotExists(fn ($q) => $q->from('payment_slips as s')->whereColumn('s.tax_declaration_id', 'd.id')
                ->whereIn('s.status', self::PAID_SLIP)->whereNull('s.cancelled_at'));
    }

    /** @return array<int, float> taxpayer_id ➜ total impayé */
    public function unpaidByTaxpayer(?array $ids = null): array
    {
        return $this->unpaidQuery()->when($ids !== null, fn ($q) => $q->whereIn('d.taxpayer_id', $ids))
            ->groupBy('d.taxpayer_id')->selectRaw('d.taxpayer_id, ROUND(COALESCE(SUM(d.total), 0), 2) s')
            ->pluck('s', 'taxpayer_id')->map(fn ($v) => (float) $v)->all();
    }

    /** @return array<int, list<array{code: string, label: string, nb: int}>> */
    public function modulesByTaxpayer(?array $ids = null): array
    {
        $out = [];
        foreach (self::MODULES as $code => [$table, $label]) {
            $rows = DB::table($table)->whereNotNull('taxpayer_id')->when($ids !== null, fn ($q) => $q->whereIn('taxpayer_id', $ids))
                ->groupBy('taxpayer_id')->selectRaw('taxpayer_id, COUNT(*) c')->pluck('c', 'taxpayer_id');
            foreach ($rows as $id => $n) {
                $out[$id][] = ['code' => $code, 'label' => $label, 'nb' => (int) $n];
            }
        }

        return $out;
    }

    /** Liste filtrée (Flask contribuables) : recherche sur 8 champs, filtre par module ou « AUCUN ». */
    public function list(string $search = '', string $module = ''): array
    {
        $q = Taxpayer::where('is_active', true);
        if (($s = trim($search)) !== '') {
            $q->where(fn ($w) => $w->where('last_name', 'like', "%$s%")->orWhere('first_name', 'like', "%$s%")->orWhere('number', 'like', "%$s%")
                ->orWhere('company_name', 'like', "%$s%")->orWhere('cin', 'like', "%$s%")->orWhere('last_name_ar', 'like', "%$s%")
                ->orWhere('phone', 'like', "%$s%")->orWhere('address', 'like', "%$s%"));
        }
        $rows = $q->orderByDesc('created_at')->orderByDesc('id')->get();
        $unpaid = $this->unpaidByTaxpayer();
        $modules = $this->modulesByTaxpayer();
        $items = [];
        foreach ($rows as $t) {
            $t->setAttribute('unpaid', $unpaid[$t->id] ?? 0.0);
            $t->setAttribute('modules', $modules[$t->id] ?? []);
            $codes = array_column($t->modules, 'code');
            if (($module === 'AUCUN' && $codes) || ($module !== '' && $module !== 'AUCUN' && ! in_array($module, $codes, true))) {
                continue;
            }
            $items[] = $t;
        }

        return ['items' => $items, 'total_unpaid' => round(array_sum(array_map(fn ($t) => $t->unpaid, $items)), 2)];
    }

    /** Fiche (Flask ctb_detail). */
    public function detail(Taxpayer $t): array
    {
        $byModule = $this->unpaidQuery()->where('d.taxpayer_id', $t->id)->groupBy('d.module')
            ->selectRaw('d.module, ROUND(COALESCE(SUM(d.total), 0), 2) s')->pluck('s', 'module');
        $unpaid = [];
        foreach (self::MODULES as $code => [, $label]) {
            if (($v = (float) ($byModule[$code] ?? 0)) > 0) {
                $unpaid[$code] = ['label' => $label, 'montant' => $v];
            }
        }
        $declarations = DB::table('tax_declarations as d')
            ->leftJoin('payment_slips as s', fn ($j) => $j->on('s.tax_declaration_id', '=', 'd.id')->whereNull('s.cancelled_at'))
            ->where('d.taxpayer_id', $t->id)->orderByDesc('d.created_at')->orderByDesc('d.id')->limit(50)
            ->get(['d.id', 'd.number', 'd.module', 'd.year', 'd.quarter', 'd.total', 'd.status', 'd.cancelled_at', 'd.declared_on', 's.status as slip_status', 's.slip_number', 's.amount as slip_amount']);
        $slips = DB::table('payment_slips as s')->leftJoin('tax_declarations as d', 'd.id', '=', 's.tax_declaration_id')
            ->where('s.taxpayer_id', $t->id)->orderByDesc('s.created_at')->orderByDesc('s.id')->limit(30)
            ->get(['s.id', 's.slip_number', 's.amount', 's.status', 's.paid_on', 's.receipt_number', 's.receipt_date', 's.cancelled_at', 'd.module', 'd.year', 'd.number as decl_number']);

        return [
            'modules' => $this->modulesByTaxpayer([$t->id])[$t->id] ?? [],
            'unpaid' => $unpaid, 'total_unpaid' => round(array_sum(array_column($unpaid, 'montant')), 2),
            'declarations' => $declarations, 'slips' => $slips,
            'total_collected' => (float) $slips->filter(fn ($s) => in_array($s->status, self::PAID_SLIP, true) && ! $s->cancelled_at)->sum('amount'),
        ];
    }

    /** Avis de non-paiement global, tous modules (Flask ctb_avis_non_paiement). */
    public function notice(Taxpayer $t): array
    {
        $rows = $this->unpaidQuery()->where('d.taxpayer_id', $t->id)->whereIn('d.module', array_keys(self::MODULES))
            ->orderBy('d.module')->orderBy('d.year')->orderBy('d.quarter')
            ->get(['d.module', 'd.year', 'd.quarter', 'd.total', 'd.principal', 'd.late_penalty', 'd.surcharge', 'd.non_filing_fine', 'd.due_on']);
        $groups = [];
        foreach ($rows as $r) {
            $groups[$r->module] ??= ['label' => self::MODULES[$r->module][1], 'lignes' => [], 'total' => 0.0];
            $groups[$r->module]['lignes'][] = ['annee' => (int) $r->year, 'trimestre' => (int) $r->quarter, 'principal' => (float) $r->principal,
                'penalite' => (float) $r->late_penalty, 'majoration' => (float) $r->surcharge, 'amende' => (float) $r->non_filing_fine,
                'total' => (float) $r->total, 'echeance' => $r->due_on];
            $groups[$r->module]['total'] += (float) $r->total;
        }
        ksort($groups);
        foreach ($groups as &$g) {
            $g['periode'] = self::period($g['lignes']);
        }
        unset($g);
        $all = array_merge(...array_values(array_column($groups, 'lignes')) ?: [[]]);

        return ['groups' => $groups, 'total' => round(array_sum(array_column($all, 'total')), 2), 'periode' => self::period($all),
            'number' => (DB::table('non_payment_notices')->count() + 1).'/'.now()->year, 'deadline' => now()->year.'-12-31'];
    }

    /** Flask format_periode_non_payee, cas « année + trimestre » (trimestre 0 lu comme 1). */
    public static function period(array $lines): array
    {
        if (! $lines) {
            return ['fr' => 'Aucune période impayée', 'ar' => 'لا توجد فترات غير مسواة', 'n' => 0];
        }
        usort($lines, fn ($a, $b) => [$a['annee'], $a['trimestre'] ?: 1] <=> [$b['annee'], $b['trimestre'] ?: 1]);
        $f = reset($lines);
        $l = end($lines);
        [$t1, $y1, $t2, $y2] = [$f['trimestre'] ?: 1, $f['annee'], $l['trimestre'] ?: 1, $l['annee']];
        if (count($lines) === 1 || ($t1 === $t2 && $y1 === $y2)) {
            return ['fr' => "Trimestre $t1 de l'année $y1", 'ar' => "عن الفصل $t1 لسنة $y1", 'n' => count($lines)];
        }

        return ['fr' => "Du Trimestre $t1 $y1 au Trimestre $t2 $y2", 'ar' => "من الفصل $t1 لسنة $y1 إلى الفصل $t2 لسنة $y2", 'n' => count($lines)];
    }

    public static function clean(array $data): array
    {
        $data = array_map(fn ($v) => is_string($v) ? (trim($v) === '' ? null : trim($v)) : $v, $data);
        if (! empty($data['cin'])) {
            $data['cin'] = mb_strtoupper($data['cin']);
        }

        return $data;
    }

    /** Création (Flask ajouter_contribuable) : n° CTB{année}{n} unique. */
    public function create(array $data, User $user): Taxpayer
    {
        $t = DB::transaction(fn () => Taxpayer::create(self::clean($data) + [
            'number' => app(NumberGenerator::class)->next('taxpayers', 'number', 'CTB'),
            'is_active' => true, 'municipality_id' => Municipality::value('id'),
        ]));
        activity('contribuables')->causedBy($user)->performedOn($t)->log("Contribuable {$t->number} créé");

        return $t;
    }

    public function update(Taxpayer $t, array $data, User $user): void
    {
        $t->update(self::clean($data));
        activity('contribuables')->causedBy($user)->performedOn($t)->withProperties(['modifications' => array_keys($t->getChanges())])->log("Contribuable {$t->number} modifié");
    }

    /** Flask supprimer_contribuable : désactivation (le contribuable et son historique sont conservés). */
    public function deactivate(Taxpayer $t, User $user): void
    {
        $t->update(['is_active' => false]);
        activity('contribuables')->causedBy($user)->performedOn($t)->log("Contribuable {$t->number} retiré du répertoire");
    }

    /** Contribuable actif de même CIN (Flask ajout rapide : pas de doublon). */
    public function findByCin(?string $cin, ?int $except = null): ?Taxpayer
    {
        $cin = mb_strtoupper(trim((string) $cin));

        return $cin === '' ? null : Taxpayer::where('is_active', true)->where('cin', $cin)->when($except, fn ($q) => $q->where('id', '!=', $except))->first();
    }
}
