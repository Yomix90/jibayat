<?php

namespace App\Http\Controllers\Caisse;

use App\Domain\Contribuables\Services\TaxpayerService;
use App\Http\Controllers\Controller;
use App\Models\Municipality;
use App\Models\Taxpayer;
use App\Models\TaxpayerDocument;
use App\Support\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;

/** Contribuables : export Excel, impression du répertoire, avis global, documents (Flask contribuables.py). */
class TaxpayerController extends Controller
{
    public function export(Request $request, TaxpayerService $service): Response
    {
        $items = $service->list((string) $request->query('q', ''), (string) $request->query('module', ''))['items'];
        activity('contribuables')->causedBy($request->user())->log('Export Excel des contribuables');
        $book = new Spreadsheet;
        $ws = $book->getActiveSheet()->setTitle('Contribuables');
        $ws->fromArray(['N°', 'Nom', 'Prénom', 'الاسم', 'Raison sociale', 'CIN', 'ICE', 'RC', 'Téléphone', 'E-mail', 'Adresse', 'Ville', 'Type', 'Taxes', 'Impayé (DH)', 'Enregistré le']);
        $ws->getStyle('A1:P1')->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1E2A3A']]]);
        $r = 2;
        foreach ($items as $t) {
            $ws->fromArray([$t->number, $t->last_name, $t->first_name, $t->displayNameAr(), $t->company_name, $t->cin, $t->ice, $t->rc, $t->phone, $t->email, $t->address, $t->city,
                $t->person_type, implode(', ', array_column($t->modules, 'label')), $t->unpaid, $t->created_at?->format('d/m/Y')], null, 'A'.$r++);
        }
        $ws->getStyle("O2:O$r")->getNumberFormat()->setFormatCode('#,##0.00');
        foreach (range('A', 'P') as $c) {
            $ws->getColumnDimension($c)->setAutoSize(true);
        }

        return response()->streamDownload(fn () => (new Xlsx($book))->save('php://output'), 'contribuables_'.now()->format('Ymd').'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function print(Request $request, TaxpayerService $service): Response
    {
        $list = $service->list((string) $request->query('q', ''), (string) $request->query('module', ''));
        usort($list['items'], fn ($a, $b) => [$a->last_name, $a->first_name] <=> [$b->last_name, $b->first_name]);

        return Pdf::render('print.caisse.contribuables', $list + ['filters' => array_filter($request->only('q', 'module'))], 'Contribuables.pdf', 'A4-L');
    }

    public function notice(Taxpayer $taxpayer, TaxpayerService $service): Response
    {
        return Pdf::render('print.caisse.avis-contribuable', $service->notice($taxpayer) + ['t' => $taxpayer, 'commune' => Municipality::first()],
            "Avis_{$taxpayer->number}.pdf");
    }

    public function document(TaxpayerDocument $document)
    {
        abort_unless($document->path && Storage::disk('local')->exists($document->path), 404, 'Fichier introuvable.');

        return Storage::disk('local')->download($document->path, $document->file_name);
    }
}
