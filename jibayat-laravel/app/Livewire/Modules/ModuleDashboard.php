<?php

namespace App\Livewire\Modules;

use App\Domain\Dashboard\DashboardStats;
use App\Livewire\AppPage;
use App\Support\PyMath;
use Illuminate\Support\Facades\DB;

/**
 * Tableau de bord d'un module (Flask : /module/<module>/dashboard) — réutilisé par chaque module :
 * dossiers, déclarations de l'année, recouvré, impayé, taux, avis ; courbe mensuelle ; statuts ;
 * historique par année ; déclarations récentes. Chaque sous-classe fixe MODULE et ROUTE.
 */
abstract class ModuleDashboard extends AppPage
{
    public function render()
    {
        $code = static::MODULE;
        $cfg = DashboardStats::MODULES[$code];
        $year = (int) now()->year;
        $decl = fn () => DB::table('tax_declarations')->where('module', $code);
        $paid = fn () => DB::table('payment_slips as s')->join('tax_declarations as d', 'd.id', '=', 's.tax_declaration_id')
            ->where('d.module', $code)->where('s.status', 'paye')->whereNull('s.cancelled_at');

        $totalEmitted = (float) $decl()->where('status', '!=', 'annule')->sum('total');
        $totalPaid = (float) $paid()->sum('s.amount');

        $kpi = [
            'files' => DB::table($cfg['table'])->where($cfg['where'])->count(),
            'declarations_year' => $decl()->where('year', $year)->count(),
            'emitted_year' => $decl()->where('year', $year)->where('status', 'emis')->count(),
            'below_threshold_year' => $decl()->where('year', $year)->where('status', 'sous_seuil')->count(),
            'paid' => $totalPaid,
            'paid_count' => $paid()->count(),
            'unpaid' => (float) $decl()->whereNotIn('status', ['paye', 'annule'])->sum('total'),
            'unpaid_count' => $decl()->whereNotIn('status', ['paye', 'annule'])->count(),
            'rate' => DashboardStats::rate($totalPaid, $totalEmitted),
            'notices' => DB::table('non_payment_notices as n')->join('tax_declarations as d', 'd.id', '=', 'n.tax_declaration_id')
                ->where('d.module', $code)->where('n.status', 'emis')->count(),
        ];

        $monthlyEmitted = array_fill(0, 12, 0.0);
        $monthlyPaid = array_fill(0, 12, 0.0);
        $decl()->where('status', '!=', 'annule')->whereYear('declared_on', $year)
            ->selectRaw('MONTH(declared_on) m, SUM(total) s')->groupByRaw('MONTH(declared_on)')->pluck('s', 'm')
            ->each(function ($s, $m) use (&$monthlyEmitted) {
                $monthlyEmitted[$m - 1] = PyMath::round((float) $s, 2);
            });
        $paid()->whereYear('s.paid_on', $year)->selectRaw('MONTH(s.paid_on) m, SUM(s.amount) s')->groupByRaw('MONTH(s.paid_on)')->pluck('s', 'm')
            ->each(function ($s, $m) use (&$monthlyPaid) {
                $monthlyPaid[$m - 1] = PyMath::round((float) $s, 2);
            });

        $statuses = $decl()->selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status');
        $byYear = $decl()->where('status', '!=', 'annule')
            ->selectRaw("year, COUNT(*) n, SUM(total) emitted, SUM(CASE WHEN status='paye' THEN total ELSE 0 END) paid")
            ->groupBy('year')->orderByDesc('year')->get();

        return view('livewire.modules.dashboard', [
            'cfg' => $cfg + ['colors' => config("navigation.{$cfg['space']}.colors")],
            'year' => $year,
            'kpi' => $kpi,
            'monthly' => ['labels' => array_map('tr', DashboardStats::MONTHS), 'series' => [__('Émis'), __('Encaissé')], 'emitted' => $monthlyEmitted, 'paid' => $monthlyPaid],
            'statuses' => ['labels' => $statuses->keys()->map(fn ($s) => ['emis' => 'Émis', 'paye' => 'Payé', 'sous_seuil' => 'Sous seuil', 'annule' => 'Annulé'][$s] ?? $s)->values(),
                'data' => $statuses->values(), 'colors' => $statuses->keys()->map(fn ($s) => ['emis' => '#C8601E', 'paye' => '#16A34A', 'sous_seuil' => '#9CA3AF', 'annule' => '#1F2933'][$s] ?? '#5B7590')->values()],
            'byYear' => $byYear,
            'recent' => DB::table('tax_declarations as d')->leftJoin('taxpayers as t', 't.id', '=', 'd.taxpayer_id')
                ->where('d.module', $code)->orderByDesc('d.id')->limit(8)
                ->get(['d.number', 'd.year', 'd.quarter', 'd.total', 'd.status', 'd.notes', 't.last_name', 't.first_name', 't.company_name']),
        ]);
    }
}
