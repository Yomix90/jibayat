<?php

namespace App\Http\Controllers\Souk;

use App\Domain\Souk\Models\SoukContract;
use App\Domain\Souk\Models\SoukContractDocument;
use App\Domain\Souk\Services\SoukCalculator;
use App\Http\Controllers\Controller;
use App\Livewire\Souk\Contrats;
use App\Models\Municipality;
use App\Support\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Impressions Souk — Flask : /souks/pdf, /souks/<id>/avis-non-paiement, documents. */
class PrintController extends Controller
{
    public function register(Request $request, SoukCalculator $calc)
    {
        $situation = (string) $request->query('situation', '');
        $rows = Contrats::rows($calc, (string) $request->query('q', ''), (string) $request->query('souk', ''))
            ->filter(fn ($r) => match ($situation) {
                'impaye' => $r['nb_non_declares'] > 0, 'en_attente' => $r['nb_emises'] > 0, 'ajour' => $r['nb_mois_impaye'] === 0, default => true,
            })->sortBy(fn ($r) => (int) $r['contract']->number)->values();

        return Pdf::render('print.souk.liste', ['rows' => $rows], 'contrats-affermage.pdf', 'A4-L');
    }

    public function notice(SoukContract $contract, SoukCalculator $calc)
    {
        $contract->load('taxpayer');
        $s = $calc->situation($contract);
        activity('pieces')->causedBy(auth()->user())->performedOn($contract)->log("Édition d'un avis de non-paiement souk (contrat {$contract->number})");

        return Pdf::render('print.souk.avis', [
            'contract' => $contract, 'commune' => Municipality::first(), 'groups' => SoukCalculator::groups($s['months']),
            'total' => $s['total_impaye'], 'period' => SoukCalculator::periodLabel($s['months']), 'periodAr' => SoukCalculator::periodLabel($s['months'], true),
            'number' => sprintf('%03d/%d', $contract->id, now()->year),
        ], "avis-souk-{$contract->number}.pdf");
    }

    public function document(SoukContractDocument $document)
    {
        abort_unless($document->path && Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->download($document->path, $document->file_name);
    }
}
