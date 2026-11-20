<?php

namespace App\Http\Controllers\Caisse;

use App\Domain\Caisse\Models\ReminderLetter;
use App\Domain\Caisse\Services\NoticeService;
use App\Http\Controllers\Controller;
use App\Models\Municipality;
use App\Support\Pdf;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;

/** Impression des lettres de relance et export des impayés (Flask avis.lettre_imprimer / avis_export_excel). */
class NoticeController extends Controller
{
    public function letter(ReminderLetter $letter, NoticeService $service): Response
    {
        $byTaxpayer = $service->letterLines($letter)->groupBy('taxpayer_id');

        return Pdf::render('print.caisse.lettre', ['letter' => $letter, 'byTaxpayer' => $byTaxpayer, 'commune' => Municipality::first()], "Lettre_{$letter->number}.pdf");
    }

    public function export(Request $request, NoticeService $service): Response
    {
        $rows = $service->rows($request->query('module') ?: null);
        activity('avis')->causedBy($request->user())->log('Export Excel des impayés');
        $book = new Spreadsheet;
        $ws = $book->getActiveSheet()->setTitle('Impayés');
        $ws->fromArray(['Taxe', 'N° déclaration', 'Redevable', 'CIN / ICE', 'Téléphone', 'Adresse', 'Année', 'Trimestre', 'Montant dû (DH)', 'N° avis', 'Date avis', 'Statut avis']);
        $ws->getStyle('A1:L1')->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1E2A3A']]]);
        $r = 2;
        foreach ($rows as $x) {
            $ws->fromArray([NoticeService::LABELS[$x->module] ?? $x->module, $x->decl_number, trim($x->last_name.' '.($x->first_name ?: $x->company_name)), $x->cin ?: $x->ice,
                $x->phone, $x->address, $x->year, $x->quarter ?: '', round((float) $x->total, 2), $x->notice_number, $x->notice_date, $x->notice_status ?: 'Sans avis'], null, 'A'.$r++);
        }
        $ws->getStyle("I2:I$r")->getNumberFormat()->setFormatCode('#,##0.00');
        foreach (range('A', 'L') as $c) {
            $ws->getColumnDimension($c)->setAutoSize(true);
        }

        return response()->streamDownload(fn () => (new Xlsx($book))->save('php://output'), 'impayes_'.now()->format('Ymd').'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
