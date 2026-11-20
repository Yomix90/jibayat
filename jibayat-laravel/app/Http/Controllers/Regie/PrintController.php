<?php

namespace App\Http\Controllers\Regie;

use App\Domain\Documents\Models\Employee;
use App\Domain\Documents\Models\StampBook;
use App\Domain\Documents\Services\AmountInWords;
use App\Domain\Regie\Models\ArchiveBox;
use App\Domain\Regie\Models\CashEntry;
use App\Domain\Regie\Models\CashRemittance;
use App\Domain\Regie\Models\CashReport;
use App\Domain\Regie\Models\PrintedDocument;
use App\Domain\Regie\Models\TicketDischarge;
use App\Domain\Regie\Models\TreasuryDispatch;
use App\Domain\Regie\Services\ControlService;
use App\Domain\Regie\Services\Ledger;
use App\Domain\Regie\Services\MinutesService;
use App\Domain\Regie\Services\RemittanceService;
use App\Domain\Regie\Services\StockService;
use App\Http\Controllers\Controller;
use App\Livewire\Regie\RapportAnnuel;
use App\Livewire\Regie\Rapports;
use App\Livewire\Regie\TaxeSpeciale;
use App\Models\Rubric;
use App\Models\Setting;
use App\Support\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** Impressions de la régie des recettes (PDF A4 bilingue, n° de registre et QR en pied de page). */
class PrintController extends Controller
{
    protected function period(Request $request): array
    {
        $year = (int) $request->query('annee', now()->year);
        $month = (int) $request->query('mois', now()->month);
        abort_unless($year >= 2000 && $year <= 2100 && $month >= 1 && $month <= 12, 404);

        return [$year, $month];
    }

    protected function meta(): array
    {
        return ['regisseur' => (string) Setting::get('regie.regisseur', ''), 'comptable' => RemittanceService::accountant(), 'rib' => (string) Setting::get('regie.rib', '')];
    }

    public function livre(Request $request, Ledger $ledger)
    {
        [$y, $m] = $this->period($request);

        return Pdf::render('print.regie.livre-caisse', ['book' => $ledger->cashBook($y, $m), 'year' => $y, 'month' => $m] + $this->meta(), sprintf('Livre_de_caisse_%d_%02d.pdf', $y, $m), 'A4-L');
    }

    public function etatMensuel(Request $request, Ledger $ledger)
    {
        [$y, $m] = $this->period($request);
        $state = $ledger->monthlyState($y, $m);

        return Pdf::render('print.regie.etat-mensuel', ['state' => $state, 'year' => $y, 'month' => $m, 'words' => AmountInWords::dirhams($state['total_month'])] + $this->meta(),
            sprintf('Modele_10_%d_%02d.pdf', $y, $m));
    }

    public function operations(Request $request, Ledger $ledger)
    {
        [$y] = $this->period($request);

        return Pdf::render('print.regie.operations', ['ops' => $ledger->treasuryOperations($y), 'year' => $y] + $this->meta(), "Modele_7_$y.pdf", 'A4-L');
    }

    public function situation(Request $request, Ledger $ledger)
    {
        $date = (string) $request->query('date', today()->toDateString());
        abort_unless(strtotime($date), 404);

        return Pdf::render('print.regie.situation', ['s' => $ledger->dailySituation($date)] + $this->meta(), "Situation_$date.pdf");
    }

    public function versement(CashRemittance $remittance)
    {
        return Pdf::render('print.regie.versement', ['r' => $remittance, 'words' => AmountInWords::dirhams((float) $remittance->amount)] + $this->meta(), "Bordereau_versement_{$remittance->number}.pdf");
    }

    public function envoi(TreasuryDispatch $dispatch)
    {
        return Pdf::render('print.regie.envoi', ['d' => $dispatch, 'types' => RemittanceService::DISPATCH_TYPES] + $this->meta(), "Bordereau_envoi_{$dispatch->number}.pdf");
    }

    public function taxeSpeciale(Request $request)
    {
        [$y, $m] = $this->period($request);
        $rubric = Rubric::findOrFail((int) $request->query('rubrique'));
        $entries = TaxeSpeciale::entries($rubric->id, $y, $m);

        return Pdf::render('print.regie.taxe-speciale', ['rubric' => $rubric, 'entries' => $entries, 'year' => $y, 'month' => $m,
            'words' => AmountInWords::dirhams((float) $entries->sum('amount'))] + $this->meta(), sprintf('Taxe_speciale_%d_%02d.pdf', $y, $m));
    }

    public function virement(CashEntry $entry)
    {
        abort_unless($entry->payment_mode === Ledger::MINISTRY && $entry->status === 'valide' && ! $entry->isCancelled(), 404);

        return Pdf::render('print.regie.virement', ['e' => $entry->load('rubric'), 'words' => AmountInWords::dirhams((float) $entry->amount)] + $this->meta(), "Autorisation_virement_{$entry->number}.pdf");
    }

    public function pv(CashReport $report)
    {
        return Pdf::render('print.regie.pv', ['r' => $report, 't' => MinutesService::TYPES[$report->type], 'blank' => false] + $this->meta(), "{$report->number}.pdf");
    }

    public function pvBlank(string $type, StockService $stock)
    {
        abort_unless(isset(MinutesService::TYPES[$type]), 404);

        return Pdf::render('print.regie.pv', ['r' => null, 'type' => $type, 't' => MinutesService::TYPES[$type], 'blank' => true, 'stock' => $stock->state()] + $this->meta(), "PV_{$type}_vierge.pdf");
    }

    public function rapprochement(Request $request, ControlService $control)
    {
        [$y, $m] = $this->period($request);

        return Pdf::render('print.regie.rapprochement', ['r' => $control->reconciliation($y, $m)] + $this->meta(), sprintf('Rapprochement_%d_%02d.pdf', $y, $m));
    }

    public function recap(Request $request, Ledger $ledger)
    {
        $from = (string) $request->query('du', now()->startOfMonth()->toDateString());
        $to = (string) $request->query('au', today()->toDateString());

        return Pdf::render('print.regie.recap', ['r' => $ledger->recap($from, $to)] + $this->meta(), "Recap_comptable_{$from}_{$to}.pdf", 'A4-L');
    }

    public function etatStock(StockService $stock)
    {
        return Pdf::render('print.regie.etat-stock', ['state' => $stock->state()] + $this->meta(), 'Etat_de_stock_'.today()->toDateString().'.pdf');
    }

    public function decharge(TicketDischarge $discharge)
    {
        return Pdf::render('print.regie.decharge', ['d' => $discharge->load(['value', 'employee']), 'blank' => false] + $this->meta(), "Decharge_{$discharge->number}.pdf");
    }

    public function dechargeBlank()
    {
        return Pdf::render('print.regie.decharge', ['d' => null, 'blank' => true] + $this->meta(), 'Bon_de_decharge_vierge.pdf');
    }

    public function dechargeVignettes(Request $request)
    {
        $employee = Employee::with('service')->findOrFail((int) $request->query('agent'));
        $date = (string) $request->query('date');
        $books = StampBook::with('stampValue')->where('employee_id', $employee->id)
            ->when(strtotime($date), fn ($q) => $q->whereDate('assigned_on', $date))->orderBy('number')->get();
        abort_if($books->isEmpty(), 404);

        return Pdf::render('print.regie.decharge-vignettes', ['employee' => $employee, 'books' => $books, 'date' => $date] + $this->meta(), "Decharge_vignettes_{$employee->id}_$date.pdf");
    }

    public function boite(ArchiveBox $box)
    {
        return Pdf::render('print.regie.boite', ['box' => $box->load('documents')] + $this->meta(), "Boite_{$box->code}.pdf");
    }

    public function rapport(Request $request, Ledger $ledger)
    {
        $from = (string) $request->query('du', now()->startOfMonth()->toDateString());
        $to = (string) $request->query('au', today()->toDateString());
        $rubric = $request->query('rubrique') ? (int) $request->query('rubrique') : null;

        return Pdf::render('print.regie.rapport', Rapports::report($ledger, $from, $to, $rubric) + ['from' => $from, 'to' => $to] + $this->meta(), "Rapport_{$from}_{$to}.pdf");
    }

    public function tableau(Request $request, Ledger $ledger)
    {
        [$y] = $this->period($request);

        return Pdf::render('print.regie.tableau', ['t' => $ledger->yearTable($y)] + $this->meta(), "Tableau_recettes_$y.pdf", 'A4-L');
    }

    public function rapportAnnuel(Request $request, Ledger $ledger)
    {
        [$y] = $this->period($request);

        return Pdf::render('print.regie.rapport-annuel', ['r' => RapportAnnuel::report($ledger, $y)] + $this->meta(), "Rapport_annuel_$y.pdf");
    }

    public function scan(PrintedDocument $document)
    {
        abort_unless($document->scan_path && Storage::disk('local')->exists($document->scan_path), 404);

        return Storage::disk('local')->response($document->scan_path);
    }

    public function exportTableau(Request $request, Ledger $ledger)
    {
        [$y] = $this->period($request);
        $t = $ledger->yearTable($y);
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle("Recettes $y");
        $sheet->fromArray(array_merge(['Code', 'Rubrique'], array_values(Ledger::MONTHS), ['Total']), null, 'A1');
        $row = 2;
        foreach ($t['rows'] as $r) {
            $sheet->fromArray(array_merge([$r['code'], $r['label']], array_values($r['months']), [$r['total']]), null, "A$row");
            $row++;
        }
        $sheet->fromArray(array_merge(['', 'Total'], array_values($t['totals']), [$t['total']]), null, "A$row");
        $sheet->getStyle("A1:O1")->getFont()->setBold(true);
        $sheet->getStyle("A$row:O$row")->getFont()->setBold(true);
        $sheet->getStyle("C2:O$row")->getNumberFormat()->setFormatCode('#,##0.00');
        foreach (range('A', 'O') as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }

        return response()->streamDownload(fn () => (new Xlsx($book))->save('php://output'), "Tableau_recettes_$y.xlsx",
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function exportIntelligence(StockService $stock)
    {
        $i = $stock->intelligence();
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Stock');
        $head = array_merge(['Valeur', 'P.U.', 'Réserve TGR', 'Régie', 'Chez les agents', 'Consommé'], $i['months'],
            ['Moyenne / mois', 'Couverture régie (mois)', 'Couverture TGR (mois)', 'Retrait conseillé', 'Valeur (DH)', 'Niveau']);
        $sheet->fromArray($head, null, 'A1');
        $row = 2;
        foreach ($i['rows'] as $r) {
            $sheet->fromArray(array_merge([$r['label'], $r['unit_value'], $r['tgr'], $r['regie'], $r['with_agents'], $r['consumed']], array_values($r['series']),
                [$r['average'], $r['cover'], $r['tgr_cover'], $r['order'], $r['order_value'], $r['level']]), null, "A$row");
            $row++;
        }
        $sheet->getStyle('1:1')->getFont()->setBold(true);
        activity('regie')->causedBy(auth()->user())->log('Export Excel — intelligence du stock');

        return response()->streamDownload(fn () => (new Xlsx($book))->save('php://output'), 'Intelligence_stock_'.today()->toDateString().'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
