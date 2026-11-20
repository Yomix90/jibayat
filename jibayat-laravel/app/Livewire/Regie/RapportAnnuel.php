<?php

namespace App\Livewire\Regie;

use App\Domain\Regie\Services\Ledger;
use App\Livewire\AppPage;
use App\Livewire\Regie\Concerns\HasPeriod;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;

/**
 * Rapport annuel de l'exercice : recettes par famille et rubrique comparées à l'année précédente et aux
 * prévisions du budget, versements au comptable, mois clôturés, procès-verbaux, pièces annulées.
 */
#[Title('Rapport annuel')]
class RapportAnnuel extends AppPage
{
    use HasPeriod;

    public const ROUTE = 'regie.rapport-annuel';

    public static function report(Ledger $ledger, int $year): array
    {
        $cur = $ledger->yearTable($year);
        $prev = collect($ledger->yearTable($year - 1)['rows'])->keyBy('code');
        $budget = DB::table('budget_lines')->where('year', $year)->whereNotNull('gir_code')->pluck('proposed', 'gir_code');
        $rows = collect($cur['rows'])->map(fn ($r) => $r + [
            'previous' => (float) ($prev[$r['code']]['total'] ?? 0),
            'evolution' => ($p = (float) ($prev[$r['code']]['total'] ?? 0)) > 0 ? round(($r['total'] - $p) / $p * 100, 1) : null,
            'budget' => isset($budget[$r['code']]) ? (float) $budget[$r['code']] : null,
        ])->all();

        return [
            'year' => $year, 'rows' => $rows, 'total' => $cur['total'], 'previous_total' => round($prev->sum('total'), 2),
            'months' => $cur['totals'],
            'families' => collect($rows)->groupBy(fn ($r) => $r['family'] ?: 'autres')->map(fn ($g) => round($g->sum('total'), 2))->sortDesc()->all(),
            'remitted' => round((float) DB::table('cash_remittances')->whereNull('cancelled_at')->whereYear('remitted_on', $year)->sum('amount'), 2),
            'closed' => DB::table('cash_closures')->where('year', $year)->whereNull('reopened_at')->count(),
            'reports' => DB::table('cash_reports')->whereNull('cancelled_at')->where('year', $year)->selectRaw('type, COUNT(*) n')->groupBy('type')->pluck('n', 'type')->all(),
            'cancelled' => DB::table('cash_entries')->whereNotNull('cancelled_at')->whereYear('entry_date', $year)->count()
                + DB::table('payment_slips')->whereNotNull('cancelled_at')->whereYear('cancelled_at', $year)->count(),
            'closing' => $ledger->balanceAt(min("$year-12-31", today()->toDateString())),
        ];
    }

    public function render(Ledger $ledger)
    {
        return view('livewire.regie.rapport-annuel', ['r' => self::report($ledger, $this->year)]);
    }
}
