<?php

namespace App\Http\Controllers\Documents;

use App\Domain\Documents\Models\IssueSlip;
use App\Domain\Documents\Models\TreasuryStatement;
use App\Domain\Documents\Services\AmountInWords;
use App\Domain\Documents\Services\IssueSlipService;
use App\Domain\Documents\Services\TgrStatementParser;
use App\Http\Controllers\Controller;
use App\Livewire\Documents\RelevesGir;
use App\Support\Pdf;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;

/** Impressions et export du Trésor : relevé GIR (Flask regie.tgr_export / tgr_pdf) et bordereaux d'émission (Flask emission PDF). */
class TresorController extends Controller
{
    protected function tgr(Request $request): array
    {
        $path = RelevesGir::storedPath($request->user()->id);
        abort_unless($path, 404, 'Aucun relevé chargé récemment.');
        $data = (new TgrStatementParser)->parse($path);
        $ops = TgrStatementParser::filter($data['operations'], $request->query('rubrique') ?: null, $request->query('debut') ?: null,
            $request->query('fin') ?: null, (string) $request->query('q', ''));

        return [$data['meta'], $ops, array_sum(array_column($ops, 'total'))];
    }

    public function tgrPdf(Request $request): Response
    {
        [$meta, $ops, $total] = $this->tgr($request);

        return Pdf::render('print.documents.releve-tgr', ['meta' => $meta, 'operations' => $ops, 'total' => $total, 'words' => AmountInWords::dirhams($total),
            'filters' => array_filter($request->only('rubrique', 'debut', 'fin', 'q'))], 'Releve_TGR.pdf', 'A4-L');
    }

    public function tgrExport(Request $request): Response
    {
        [$meta, $ops, $total] = $this->tgr($request);
        activity('tgr')->causedBy($request->user())->log('Export Excel du relevé GIR / TGR');
        $book = new Spreadsheet;
        $ws = $book->getActiveSheet()->setTitle('Activité mensuelle');
        $period = $meta['periode_debut'] ? 'Du '.date('d/m/Y', strtotime($meta['periode_debut'])).' au '.date('d/m/Y', strtotime($meta['periode_fin'])) : 'Période inconnue';
        foreach ([['Commune / Préfecture :', $meta['commune']], ['Régie :', $meta['regie']], ['Période :', $period], ['Total :', $total]] as $i => [$l, $v]) {
            $ws->setCellValue('C'.($i + 2), $l)->setCellValue('D'.($i + 2), $v);
            $ws->getStyle('C'.($i + 2))->getFont()->setBold(true)->getColor()->setRGB('2E75B6');
        }
        $ws->getStyle('D5')->getNumberFormat()->setFormatCode('#,##0.00');
        $ws->setCellValue('A6', '*')->setCellValue('B6', AmountInWords::dirhams($total))->mergeCells('B6:F6');
        $ws->getStyle('B6')->getFont()->setItalic(true)->setSize(9);
        $head = ['N°', 'Réf. paiement', 'Date', 'Partie versante', 'Mode paiement', 'Réf. créance', 'Code rubrique', 'Libellé rubrique', 'Principal', 'Majoration', 'Pénalités', 'Astreintes', 'Total'];
        $ws->fromArray($head, null, 'A8');
        $ws->getStyle('A8:M8')->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1E2A3A']]]);
        $r = 9;
        foreach ($ops as $op) {
            $ws->fromArray([$op['num'], $op['ref_paiement'], $op['date'] ? date('d/m/Y', strtotime($op['date'])) : '', $op['partie_versante'], $op['mode_paiement'], $op['ref_creance'],
                $op['code_rubrique'], $op['libelle_rubrique'], $op['principal'], $op['majoration'], $op['penalites'], $op['astreintes'], $op['total']], null, 'A'.$r++);
        }
        $ws->setCellValue('H'.$r, 'TOTAL')->setCellValue('M'.$r, $total);
        $ws->getStyle("A$r:M$r")->getFont()->setBold(true);
        $ws->getStyle('I9:M'.$r)->getNumberFormat()->setFormatCode('#,##0.00');
        foreach (['A' => 6, 'B' => 14, 'C' => 12, 'D' => 34, 'E' => 16, 'F' => 14, 'G' => 14, 'H' => 48, 'I' => 13, 'J' => 13, 'K' => 13, 'L' => 13, 'M' => 14] as $c => $w) {
            $ws->getColumnDimension($c)->setWidth($w);
        }
        $name = 'Activite_mensuelle_'.($meta['periode_debut'] ? str_replace('-', '', $meta['periode_debut']) : 'export').'.xlsx';

        return response()->streamDownload(fn () => (new Xlsx($book))->save('php://output'), $name,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function slip(IssueSlip $slip, IssueSlipService $service): Response
    {
        return Pdf::render('print.documents.bordereau-emission', ['slips' => [$service->slipData($slip)]], "BE_{$slip->treasuryStatement->year}_{$slip->number}.pdf");
    }

    public function month(int $year, int $month, IssueSlipService $service): Response
    {
        $st = TreasuryStatement::where('year', $year)->where('month', $month)->firstOrFail();
        $slips = $st->issueSlips()->orderBy('number')->get()->map(fn ($s) => $service->slipData($s->setRelation('treasuryStatement', $st)))->all();
        abort_unless($slips, 404);

        return Pdf::render('print.documents.bordereau-emission', ['slips' => $slips], sprintf('Bordereaux_Emission_%d_%02d.pdf', $year, $month));
    }
}
