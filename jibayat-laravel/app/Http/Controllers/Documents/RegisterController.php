<?php

namespace App\Http\Controllers\Documents;

use App\Domain\Documents\Services\RegisterService;
use App\Http\Controllers\Controller;
use App\Support\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Exports CSV et impressions des registres — Flask /registre/export/<module> et /registre/export_declarations/<module>. */
class RegisterController extends Controller
{
    /** CSV (séparateur « ; », UTF-8 avec BOM pour Excel). */
    public function export(Request $request, string $type, RegisterService $service): StreamedResponse
    {
        abort_unless(in_array($type, ['paiements', 'declarations'], true), 404);
        $year = $request->query('annee') ?: null;
        $module = $request->query('module') ?: null;
        $search = trim((string) $request->query('q', ''));
        activity('pieces')->causedBy($request->user())->withProperties(compact('type', 'year', 'module'))->log("Export CSV du registre ({$type})");

        if ($type === 'paiements') {
            $rows = $service->paymentsQuery($year, $module, (string) $request->query('statut', 'all'), $search)
                ->orderByDesc('d.year')->orderByDesc('d.id')->select(RegisterService::paymentColumns())->cursor();
            $head = ['N° déclaration', 'Taxe', 'Année', 'Trimestre', 'Redevable', 'CIN', 'Montant émis', 'N° BV', 'N° quittance', 'Date quittance', 'Montant recouvré', 'Statut'];
            $map = fn ($r) => [$r->number, RegisterService::MODULES[$r->module]['fr'] ?? $r->module, $r->year, $r->quarter ?: '', RegisterService::taxpayerName($r), $r->cin,
                number_format((float) $r->total, 2, ',', ''), $r->slip_number, $r->receipt_number, $r->receipt_date,
                ($r->slip_status === 'paye' || $r->status === 'paye') ? number_format((float) ($r->amount ?? 0), 2, ',', '') : '0,00', $r->status];
        } else {
            $rows = $service->declarationsQuery($year, $module, $search)->orderByDesc('d.year')->orderByDesc('d.id')
                ->select(['d.number', 'd.module', 'd.year', 'd.quarter', 'd.base_amount', 'd.rate', 'd.principal', 'd.late_penalty', 'd.surcharge',
                    'd.non_filing_fine', 'd.total', 'd.status', 'd.declared_on', 't.last_name', 't.first_name', 't.company_name', 't.cin'])->cursor();
            $head = ['N° déclaration', 'Taxe', 'Année', 'Trimestre', 'Redevable', 'CIN', 'Base', 'Taux', 'Principal', 'Pénalité', 'Majoration', 'Amende', 'Total', 'Statut', 'Date'];
            $map = fn ($r) => [$r->number, RegisterService::MODULES[$r->module]['fr'] ?? $r->module, $r->year, $r->quarter ?: '', RegisterService::taxpayerName($r), $r->cin,
                ...array_map(fn ($v) => number_format((float) $v, 2, ',', ''), [$r->base_amount, $r->rate, $r->principal, $r->late_penalty, $r->surcharge, $r->non_filing_fine, $r->total]),
                $r->status, $r->declared_on];
        }

        return response()->streamDownload(function () use ($rows, $head, $map) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $head, ';');
            foreach ($rows as $r) {
                fputcsv($out, $map($r), ';');
            }
            fclose($out);
        }, "registre-{$type}-".($year ?? 'toutes').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function grandLivre(Request $request, RegisterService $service)
    {
        $year = $request->query('annee') ?: null;
        $module = $request->query('module') ?: null;
        $q = $service->paymentsQuery($year, $module, (string) $request->query('statut', 'all'), trim((string) $request->query('q', '')));

        return Pdf::render('print.documents.grand-livre', [
            'rows' => (clone $q)->orderBy('d.module')->orderByDesc('d.year')->orderBy('d.id')->select(RegisterService::paymentColumns())->get(),
            'totals' => $service->paymentTotals($q), 'year' => $year, 'module' => $module,
        ], 'grand-livre-des-recettes.pdf', 'A4-L');
    }

    /** Bordereau de versement au percepteur (Flask regie.imprimer_bordereau). */
    public function remittance(\App\Domain\Documents\Models\StampRemittance $remittance)
    {
        $remittance->load('books.stampValue', 'books.employee', 'books.service');

        return Pdf::render('print.documents.bordereau', ['remittance' => $remittance], "bordereau-{$remittance->number}.pdf");
    }

    public function cancelled(Request $request, RegisterService $service)
    {
        return Pdf::render('print.documents.pieces-annulees', [
            'rows' => $service->cancelled($request->query('module') ?: null, trim((string) $request->query('q', ''))),
        ], 'pieces-annulees.pdf', 'A4-L');
    }
}
