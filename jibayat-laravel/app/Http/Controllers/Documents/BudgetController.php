<?php

namespace App\Http\Controllers\Documents;

use App\Domain\Documents\Services\BudgetService;
use App\Http\Controllers\Controller;
use App\Support\Pdf;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;

/** Budget : impression A4 et export Excel bilingues (Flask regie_budget_pdf / regie_budget_export_excel). */
class BudgetController extends Controller
{
    protected function params(Request $request): array
    {
        return [(int) ($request->query('annee') ?: now()->year), $request->query('lang') === 'ar' ? 'ar' : 'fr'];
    }

    public function pdf(Request $request, BudgetService $service): Response
    {
        [$year, $lang] = $this->params($request);
        $service->ensureYear($year);

        return Pdf::render('print.documents.budget', $service->overview($year) + ['year' => $year, 'lang' => $lang], "Budget_$year.pdf", 'A4-L');
    }

    public function export(Request $request, BudgetService $service): Response
    {
        [$year, $lang] = $this->params($request);
        $service->ensureYear($year);
        $o = $service->overview($year);
        $fr = $lang === 'fr';
        activity('budget')->causedBy($request->user())->log("Export Excel du budget $year");

        $book = new Spreadsheet;
        $ws = $book->getActiveSheet()->setTitle(($fr ? 'Budget ' : 'الميزانية ').$year)->setRightToLeft(! $fr);
        $c = \App\Models\Municipality::first();
        $ws->setCellValue('A1', $fr ? "Royaume du Maroc — Ministère de l'Intérieur" : 'المملكة المغربية — وزارة الداخلية');
        $ws->setCellValue('A2', $fr ? 'Province de '.($c?->province ?? '').' — '.($c?->name ?? '') : 'إقليم '.($c?->province_ar ?? '').' — '.($c?->name_ar ?? ''));
        $ws->setCellValue('A3', $fr ? 'Secrétariat Général — Régie des Recettes' : 'الكتابة العامة — شساعة المداخيل');
        $ws->setCellValue('A5', $fr ? "Projet de Budget des Recettes $year & Réalisations Effectives" : "مشروع ميزانية المداخيل لسنة $year والمداخيل المحققة");
        $ws->getStyle('A5')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('1E2A3A');
        $ws->setCellValue('A6', ($fr ? "Date d'extraction : " : 'تاريخ الاستخراج : ').now()->format('d/m/Y H:i').' — JIBAYAT');
        $n1 = $o['annee_n1'];
        $head = $fr ? ['Chapitre', 'Nature des Recettes', "Réalisé $n1 (DH)", "Proposé $year (DH)", "Réalisé $year (DH)", 'Taux (%)', 'Écart (+ / -)']
            : ['الفصول', 'نوع المداخيل', "المحققة لسنة $n1 (DH)", "المقترحة لسنة $year (DH)", "المحققة لسنة $year (DH)", 'نسبة الإنجاز (%)', 'الفارق (+ / -)'];
        $ws->fromArray($head, null, 'A8');
        $ws->getStyle('A8:G8')->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1E2A3A']],
            'alignment' => ['horizontal' => 'center', 'wrapText' => true]]);
        $r = 9;
        $money = fn ($v) => round((float) $v, 2);
        foreach ($o['domaines'] as $d) {
            $label = $fr ? $d['nom_fr'] : $d['nom_ar'];
            $ws->setCellValue("A$r", $label)->mergeCells("A$r:G$r");
            $ws->getStyle("A$r")->applyFromArray(['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'F2F3F5']]]);
            $r++;
            foreach ($d['lignes'] as $l) {
                $ws->fromArray([$l->chapter, $fr ? ($l->label_fr ?: $l->label) : ($l->label_ar ?: $l->label), $money($l->previous_actual), $money($l->proposed), $money($l->actual),
                    number_format($l->rate, 1, '.', '').' %', $money($l->gap)], null, "A$r");
                $r++;
            }
            $ws->fromArray([$fr ? 'Sous-total :' : 'المجموع :', $label, $money($d['total_n1']), $money($d['total_propose']), $money($d['total_realise']),
                number_format($d['total_taux'], 1, '.', '').' %', $money($d['total_ecart'])], null, "A$r");
            $ws->getStyle("A$r:G$r")->applyFromArray(['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'E6E8EC']]]);
            $r++;
        }
        $g = $o['grand_total'];
        $ws->fromArray([$fr ? 'TOTAL GÉNÉRAL :' : 'المجموع العام :', $fr ? 'Budget global communal' : 'الميزانية الإجمالية للجماعة', $money($g['total_n1']), $money($g['total_propose']),
            $money($g['total_realise']), number_format($g['total_taux'], 1, '.', '').' %', $money($g['total_ecart'])], null, "A$r");
        $ws->getStyle("A$r:G$r")->applyFromArray(['font' => ['bold' => true, 'size' => 11], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'F6EBC8']]]);
        $ws->getStyle("C9:E$r")->getNumberFormat()->setFormatCode('#,##0.00');
        $ws->getStyle("G9:G$r")->getNumberFormat()->setFormatCode('#,##0.00');
        $r += 3;
        $ws->setCellValue("B$r", $fr ? 'Le Régisseur des Recettes' : 'شسيع المداخيل الجماعية')->setCellValue("E$r", $fr ? 'Le Président du Conseil' : 'رئيس المجلس الجماعي');
        foreach (['A' => 16, 'B' => 52, 'C' => 20, 'D' => 20, 'E' => 20, 'F' => 14, 'G' => 18] as $col => $w) {
            $ws->getColumnDimension($col)->setWidth($w);
        }

        return response()->streamDownload(fn () => (new Xlsx($book))->save('php://output'), "Budget_{$year}_{$lang}.xlsx",
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
