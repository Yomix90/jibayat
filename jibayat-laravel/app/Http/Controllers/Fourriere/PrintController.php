<?php

namespace App\Http\Controllers\Fourriere;

use App\Domain\Fourriere\Models\AuctionGroup;
use App\Domain\Fourriere\Models\AuctionSale;
use App\Http\Controllers\Controller;
use App\Livewire\Fourriere\Dossiers;
use App\Models\Municipality;
use App\Support\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Impressions fourrière — Flask : /fourriere/pdf, /fourriere/encheres/groupe/<id>/imprimer, PV de vente. */
class PrintController extends Controller
{
    public function register(Request $request)
    {
        $rows = Dossiers::rows((string) $request->query('q', ''), (string) $request->query('statut', ''), (string) $request->query('type', ''));

        return Pdf::render('print.fourriere.liste', ['rows' => $rows], 'registre-fourriere.pdf', 'A4-L');
    }

    public function group(AuctionGroup $group)
    {
        $group->load('vehicles.impoundCase');

        return Pdf::render('print.fourriere.groupe', ['group' => $group, 'commune' => Municipality::first()], "enchere-{$group->number}.pdf");
    }

    public function report(AuctionSale $sale)
    {
        abort_unless($sale->report_path && Storage::disk('local')->exists($sale->report_path), 404);

        return Storage::disk('local')->download($sale->report_path, $sale->report_name ?: 'pv-vente');
    }
}
