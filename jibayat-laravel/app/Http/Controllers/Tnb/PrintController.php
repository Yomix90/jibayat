<?php

namespace App\Http\Controllers\Tnb;

use App\Domain\Tnb\Models\Parcel;
use App\Domain\Tnb\Models\ParcelDocument;
use App\Domain\Tnb\Models\TnbFile;
use App\Domain\Tnb\Services\TnbCalculator;
use App\Http\Controllers\Controller;
use App\Livewire\Tnb\Terrains;
use App\Models\Municipality;
use App\Support\Pdf;
use App\Support\PyMath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Impressions TNB — Flask : /tnb/pdf, /tnb/<id>/avis_non_paiement, /tnb/avis_multiple.
 */
class PrintController extends Controller
{
    /** Liste des dossiers TNB (A4 paysage), mêmes filtres que l'écran. */
    public function register(Request $request, TnbCalculator $calc)
    {
        $filters = ['q' => (string) $request->query('q', ''), 'statut' => (string) $request->query('statut', ''),
            'zone' => (string) $request->query('zone', ''), 'lotissement' => (string) $request->query('lotissement', '')];
        $data = Terrains::dossiers($calc, ...array_values($filters));

        return Pdf::render('print.tnb.dossiers', $data + ['filters' => collect($filters)->filter()->implode(', ')], 'dossiers-tnb.pdf', 'A4-L');
    }

    /** Un avis (terrain) ou un lot d'avis (?ids=1,2,3), en arabe. */
    public function notices(Request $request, TnbCalculator $calc, ?Parcel $parcel = null)
    {
        $ids = $parcel ? [$parcel->id] : array_filter(array_map('intval', explode(',', (string) $request->query('ids', ''))));
        abort_if(! $ids, 404);

        $notices = [];
        $parcels = Parcel::with('taxpayer')->whereIn('id', $ids)->get()->sortBy(fn ($p) => array_search($p->id, $ids));
        foreach ($parcels as $p) {
            $years = array_map(fn ($y) => $calc->computeYear((string) ($p->zone ?? 'A'), (float) $p->area, $y, null, $p->id) + ['annee' => $y], $calc->dueYears($p));
            if (! $years && ! $parcel) {
                continue;   // Flask avis_multiple : terrain sans année due ignoré
            }
            $notices[] = [
                'parcel' => $p, 'years' => $years,
                'total' => PyMath::round(array_sum(array_column($years, 'total')), 2),
                'periodAr' => TnbCalculator::periodLabel(array_column($years, 'annee'), true),
                'file' => TnbFile::where('taxpayer_id', $p->taxpayer_id)->value('number'),
                'number' => (count($notices) + 1).'/'.now()->year,
            ];
            activity('pieces')->causedBy($request->user())->performedOn($p)->log("Édition d'un avis de non-paiement TNB (terrain {$p->number})");
        }
        abort_if(! $notices, 404, 'Aucun terrain avec des années dues.');

        return Pdf::render('print.tnb.avis', ['notices' => $notices, 'commune' => Municipality::first()],
            $parcel ? "avis-tnb-terrain-{$parcel->number}.pdf" : 'avis-tnb-lot.pdf');
    }

    /** Téléchargement d'un document joint à un terrain (stockage privé). */
    public function document(ParcelDocument $document)
    {
        abort_unless($document->path && Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->download($document->path, $document->file_name);
    }
}
