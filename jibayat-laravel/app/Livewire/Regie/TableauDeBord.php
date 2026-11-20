<?php

namespace App\Livewire\Regie;

use App\Domain\Regie\Services\ControlService;
use App\Domain\Regie\Services\Ledger;
use App\Domain\Regie\Services\MonthLock;
use App\Livewire\AppPage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;

/** Tableau de bord de la régie : encaisse, recettes du jour et du mois, 14 derniers jours, points à corriger. */
#[Title('Régie des recettes')]
class TableauDeBord extends AppPage
{
    public const ROUTE = 'regie.tableau-de-bord';

    public function render(Ledger $ledger, ControlService $control)
    {
        $today = CarbonImmutable::today();
        $from = $today->subDays(13);
        $receipts = $ledger->receipts($from->toDateString(), $today->toDateString());
        $days = [];
        for ($d = $from; $d->lte($today); $d = $d->addDay()) {
            $days[] = ['date' => $d, 'amount' => round($receipts->where('date', $d->toDateString())->sum('amount'), 2)];
        }
        $month = $ledger->receipts($today->startOfMonth()->toDateString(), $today->toDateString());

        return view('livewire.regie.tableau-de-bord', [
            'balance' => $ledger->balanceAt($today->toDateString()),
            'todayTotal' => round($receipts->where('date', $today->toDateString())->sum('amount'), 2),
            'monthTotal' => round($month->sum('amount'), 2),
            'byFamily' => $month->groupBy('family')->map(fn ($g) => round($g->sum('amount'), 2))->sortDesc()->all(),
            'drafts' => DB::table('cash_entries')->where('status', 'brouillon')->whereNull('cancelled_at')->count(),
            'days' => $days, 'issues' => array_slice($control->issues($today), 0, 8),
            'closed' => MonthLock::closure($today->subMonth()->year, $today->subMonth()->month),
            'ceiling' => (float) \App\Models\Setting::get('regie.plafond', 0),
        ]);
    }
}
