<?php

namespace App\Domain\Documents\Services;

use App\Support\PyMath;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Registres transverses — portage de Flask modules/registre.py :
 *   index()                ➜ moduleStats()     (hub des registres)
 *   registre_global()      ➜ paymentsQuery()   (grand livre des recettes)
 *   declarations_global()  ➜ declarationsQuery()
 */
class RegisterService
{
    public const MODULES = [
        'TNB' => ['fr' => 'Taxe sur les terrains urbains non bâtis', 'ar' => 'الرسم على الأراضي الحضرية غير المبنية', 'icon' => 'map-pin', 'color' => '#8B5A2B', 'periodicity' => 'Annuelle', 'space' => 'tnb'],
        'DEBITS_BOISSONS' => ['fr' => 'Taxe sur les débits de boissons', 'ar' => 'الرسم على محال بيع المشروبات', 'icon' => 'coffee', 'color' => '#8B2E3C', 'periodicity' => 'Trimestrielle', 'space' => 'boissons'],
        'STATIONNEMENT' => ['fr' => 'Stationnement & transport public de voyageurs', 'ar' => 'الرسم على وقوف العربات والنقل العمومي', 'icon' => 'bus', 'color' => '#5B7590', 'periodicity' => 'Trimestrielle', 'space' => 'transport'],
        'OCCUPATION_DOMAINE' => ['fr' => 'Occupation temporaire du domaine public', 'ar' => 'رسم شغل الأملاك الجماعية العامة مؤقتا', 'icon' => 'tent', 'color' => '#2F7D6B', 'periodicity' => 'Trimestrielle / annuelle', 'space' => 'domaine-public'],
        'LOCATION_LOCAUX' => ['fr' => 'Location des locaux et boutiques communaux', 'ar' => 'كراء المحلات التجارية والمهنية', 'icon' => 'store', 'color' => '#C8601E', 'periodicity' => 'Mensuelle', 'space' => 'locations'],
        'AFFERMAGE_SOUKS' => ['fr' => 'Affermage des souks', 'ar' => 'كراء وتدبير الأسواق', 'icon' => 'shopping-basket', 'color' => '#B8891F', 'periodicity' => 'Mensuelle', 'space' => 'souk'],
        'FOURRIERE' => ['fr' => 'Droits et frais de fourrière', 'ar' => 'مستحقات ورسوم المحجز الجماعي', 'icon' => 'car', 'color' => '#3F3F46', 'periodicity' => 'Au comptant', 'space' => 'fourriere'],
    ];

    /** Statistiques par module (Flask registre.index) : déclarations, émis, pénalités, quittances, recouvré, reste, taux. */
    public function moduleStats(): array
    {
        $decl = DB::table('tax_declarations')->where('status', '!=', 'annule')->whereNull('cancelled_at')
            ->groupBy('module')->selectRaw('module, COUNT(*) n, COUNT(DISTINCT taxpayer_id) taxpayers, COALESCE(SUM(principal),0) principal,
                COALESCE(SUM(late_penalty + surcharge + non_filing_fine),0) penalties, COALESCE(SUM(total),0) emitted')->get()->keyBy('module');
        $paid = DB::table('payment_slips as s')->join('tax_declarations as d', 'd.id', '=', 's.tax_declaration_id')
            ->where('s.status', 'paye')->whereNull('s.cancelled_at')
            ->groupBy('d.module')->selectRaw('d.module, COUNT(s.id) n, COALESCE(SUM(s.amount),0) amount')->get()->keyBy('module');

        $out = [];
        foreach (self::MODULES as $key => $meta) {
            $d = $decl->get($key);
            $p = $paid->get($key);
            $emitted = (float) ($d->emitted ?? 0);
            $amount = (float) ($p->amount ?? 0);
            $out[$key] = $meta + [
                'taxpayers' => (int) ($d->taxpayers ?? 0), 'declarations' => (int) ($d->n ?? 0),
                'principal' => (float) ($d->principal ?? 0), 'penalties' => (float) ($d->penalties ?? 0), 'emitted' => $emitted,
                'receipts' => (int) ($p->n ?? 0), 'paid' => $amount,
                'remaining' => max(0.0, PyMath::round($emitted - $amount, 2)),
                'rate' => $emitted > 0 ? PyMath::round($amount / $emitted * 100, 1) : 0.0,
            ];
        }

        return $out;
    }

    /** Lignes du grand livre : déclarations non annulées + leur bulletin (Flask registre_global). */
    public function paymentsQuery(?string $year, ?string $module, string $status = 'all', string $search = ''): Builder
    {
        return DB::table('tax_declarations as d')
            ->leftJoin('payment_slips as s', fn ($j) => $j->on('s.tax_declaration_id', '=', 'd.id')->whereNull('s.cancelled_at')->where('s.status', '!=', 'annule'))
            ->leftJoin('taxpayers as t', 't.id', '=', 'd.taxpayer_id')
            ->where('d.status', '!=', 'annule')->whereNull('d.cancelled_at')
            ->when($year, fn ($q) => $q->where('d.year', $year))
            ->when($module && isset(self::MODULES[$module]), fn ($q) => $q->where('d.module', $module))
            ->when($status === 'paye', fn ($q) => $q->where(fn ($w) => $w->where('s.status', 'paye')->orWhere('d.status', 'paye')))
            ->when($status === 'en_attente', fn ($q) => $q->where(fn ($w) => $w->where('s.status', 'en_attente')->orWhere(fn ($x) => $x->where('d.status', 'emis')->whereNull('s.id'))))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('d.number', 'like', "%{$search}%")->orWhere('s.slip_number', 'like', "%{$search}%")
                ->orWhere('s.receipt_number', 'like', "%{$search}%")->orWhere('t.last_name', 'like', "%{$search}%")->orWhere('t.first_name', 'like', "%{$search}%")
                ->orWhere('t.company_name', 'like', "%{$search}%")->orWhere('t.cin', 'like', "%{$search}%")->orWhere('t.ice', 'like', "%{$search}%")));
    }

    public static function paymentColumns(): array
    {
        return ['d.id', 'd.number', 'd.module', 'd.year', 'd.quarter', 'd.total', 'd.status', 'd.declared_on', 'd.notes',
            's.slip_number', 's.receipt_number', 's.receipt_date', 's.amount', 's.payment_mode', 's.status as slip_status',
            't.last_name', 't.first_name', 't.company_name', 't.cin'];
    }

    /** Totaux du grand livre : émis, recouvré (bulletins payés), taux. */
    public function paymentTotals(Builder $q): array
    {
        $r = (clone $q)->selectRaw("COUNT(*) n, COALESCE(SUM(d.total),0) emitted,
            COALESCE(SUM(CASE WHEN s.status='paye' OR d.status='paye' THEN COALESCE(s.amount, 0) ELSE 0 END),0) paid")->first();
        $emitted = (float) $r->emitted;
        $paid = (float) $r->paid;

        return ['n' => (int) $r->n, 'emitted' => $emitted, 'paid' => $paid, 'remaining' => max(0.0, PyMath::round($emitted - $paid, 2)),
            'rate' => $emitted > 0 ? PyMath::round($paid / $emitted * 100, 1) : 0.0];
    }

    /** Registre des déclarations (Flask declarations_global). */
    public function declarationsQuery(?string $year, ?string $module, string $search = ''): Builder
    {
        return DB::table('tax_declarations as d')->leftJoin('taxpayers as t', 't.id', '=', 'd.taxpayer_id')
            ->where('d.status', '!=', 'annule')->whereNull('d.cancelled_at')
            ->when($year, fn ($q) => $q->where('d.year', $year))
            ->when($module && isset(self::MODULES[$module]), fn ($q) => $q->where('d.module', $module))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('d.number', 'like', "%{$search}%")->orWhere('t.last_name', 'like', "%{$search}%")
                ->orWhere('t.first_name', 'like', "%{$search}%")->orWhere('t.company_name', 'like', "%{$search}%")->orWhere('t.cin', 'like', "%{$search}%")));
    }

    public function declarationTotals(Builder $q): array
    {
        $r = (clone $q)->selectRaw('COUNT(*) n, COALESCE(SUM(d.base_amount),0) base, COALESCE(SUM(d.principal),0) principal,
            COALESCE(SUM(d.late_penalty + d.surcharge + d.non_filing_fine),0) penalties, COALESCE(SUM(d.total),0) total')->first();

        return ['n' => (int) $r->n, 'base' => (float) $r->base, 'principal' => (float) $r->principal, 'penalties' => (float) $r->penalties, 'total' => (float) $r->total];
    }

    public static function years(): array
    {
        $years = DB::table('tax_declarations')->whereNotNull('year')->where('year', '>', 0)->distinct()->orderByDesc('year')->pluck('year')->map(fn ($y) => (int) $y)->all();

        return array_values(array_unique(array_merge([(int) now()->year], $years)));
    }

    public static function taxpayerName(object $r): string
    {
        return trim($r->company_name ?: trim(($r->last_name ?? '').' '.($r->first_name ?? ''))) ?: '—';
    }

    /** Pièces annulées : déclarations et bulletins annulés, avec motif, auteur et date. */
    public function cancelled(?string $module = null, string $search = ''): array
    {
        $decl = DB::table('tax_declarations as d')->leftJoin('users as u', 'u.id', '=', 'd.cancelled_by')->leftJoin('taxpayers as t', 't.id', '=', 'd.taxpayer_id')
            ->where(fn ($w) => $w->whereNotNull('d.cancelled_at')->orWhere('d.status', 'annule'))
            ->when($module, fn ($q) => $q->where('d.module', $module))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('d.number', 'like', "%{$search}%")->orWhere('d.cancellation_reason', 'like', "%{$search}%")))
            ->selectRaw("'Déclaration' as kind, d.number, d.module, d.total as amount, d.cancelled_at, d.cancellation_reason as reason, u.name as by_name,
                t.last_name, t.first_name, t.company_name, d.year")
            ->get();
        $slips = DB::table('payment_slips as s')->leftJoin('tax_declarations as d', 'd.id', '=', 's.tax_declaration_id')
            ->leftJoin('users as u', 'u.id', '=', 's.cancelled_by')->leftJoin('taxpayers as t', 't.id', '=', 's.taxpayer_id')
            ->where(fn ($w) => $w->whereNotNull('s.cancelled_at')->orWhere('s.status', 'annule'))
            ->when($module, fn ($q) => $q->where('d.module', $module))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('s.slip_number', 'like', "%{$search}%")->orWhere('s.cancellation_reason', 'like', "%{$search}%")))
            ->selectRaw("'Bulletin' as kind, s.slip_number as number, d.module, s.amount, s.cancelled_at, s.cancellation_reason as reason, u.name as by_name,
                t.last_name, t.first_name, t.company_name, d.year")
            ->get();

        $regie = collect();
        if (! $module || $module === 'REGIE') {
            $sources = [
                ['cash_entries', 'Recette', 'number', 'amount', 'COALESCE(x.payer, x.description)'],
                ['cash_remittances', 'Bordereau de versement', 'number', 'amount', 'x.accountant'],
                ['treasury_dispatches', 'Envoi au Trésor', 'number', '0', 'x.recipient'],
                ['cash_reports', 'Procès-verbal', 'number', 'x.counted_balance', 'x.type'],
                ['ticket_discharges', 'Décharge de tickets', 'number', '0', 'x.place'],
            ];
            foreach ($sources as [$table, $kind, $num, $amount, $who]) {
                $regie = $regie->concat(DB::table("$table as x")->leftJoin('users as u', 'u.id', '=', 'x.cancelled_by')->whereNotNull('x.cancelled_at')
                    ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where("x.$num", 'like', "%{$search}%")->orWhere('x.cancellation_reason', 'like', "%{$search}%")))
                    ->selectRaw("'$kind' as kind, x.$num as number, 'REGIE' as module, ".($amount === '0' ? '0' : (str_starts_with($amount, 'x.') ? $amount : "x.$amount")).' as amount,'
                        ." x.cancelled_at, x.cancellation_reason as reason, u.name as by_name, $who as last_name, null as first_name, null as company_name, null as year")
                    ->get());
            }
        }

        return $decl->concat($slips)->concat($regie)->sortByDesc('cancelled_at')->values()->all();
    }
}
