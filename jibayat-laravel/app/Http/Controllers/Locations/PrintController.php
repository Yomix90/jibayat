<?php

namespace App\Http\Controllers\Locations;

use App\Domain\Locations\Models\Lease;
use App\Domain\Locations\Models\LeaseSector;
use App\Domain\Locations\Services\LeaseCalculator;
use App\Http\Controllers\Controller;
use App\Models\Municipality;
use App\Support\Pdf;
use Illuminate\Http\Request;

/**
 * Impressions du module Locations (Flask : /location-locaux/pdf et avis de non-paiement).
 */
class PrintController extends Controller
{
    /** Registre des baux (A4 paysage), mêmes filtres que la liste. */
    public function register(Request $request, LeaseCalculator $calc)
    {
        $search = (string) $request->query('q', '');
        $sector = $request->query('secteur');
        $situation = (string) $request->query('situation', '');

        $rows = Lease::with(['taxpayer', 'shop', 'leaseSector', 'leaseTariff'])->where('is_active', true)
            ->when($sector, fn ($q) => $q->where('lease_sector_id', $sector))
            ->when($search, fn ($q) => $q->where(fn ($w) => $w->where('number', 'like', "%{$search}%")->orWhere('premises_ref', 'like', "%{$search}%")
                ->orWhereHas('taxpayer', fn ($t) => $t->where('last_name', 'like', "%{$search}%")->orWhere('cin', 'like', "%{$search}%"))))
            ->orderByRaw('CAST(number AS UNSIGNED), number')->get()
            ->map(fn (Lease $l) => ['lease' => $l, 'rent' => $calc->monthlyRent($l)] + $calc->situation($l))
            ->filter(fn ($r) => match ($situation) {
                'impaye' => $r['nb_non_declares'] > 0, 'en_attente' => $r['nb_emises'] > 0, 'ajour' => $r['nb_non_paye'] === 0, default => true,
            })->values();

        $filters = collect([
            $search ? "« {$search} »" : null,
            $sector ? 'secteur '.LeaseSector::find($sector)?->label : null,
            ['impaye' => 'avec impayés', 'en_attente' => 'en attente', 'ajour' => 'à jour'][$situation] ?? null,
        ])->filter()->implode(', ');

        return Pdf::render('print.locations.repertoire', compact('rows', 'filters'), 'registre-des-baux.pdf', 'A4-L');
    }

    /** Avis (mise en demeure) de non-paiement d'un bail, en arabe (Flask : ctb_avis_non_paiement). */
    public function notice(Lease $lease, LeaseCalculator $calc)
    {
        $lease->load(['taxpayer', 'shop']);
        $months = $calc->unpaidMonths($lease);
        activity('pieces')->causedBy(auth()->user())->performedOn($lease)->log("Édition d'un avis de non-paiement (bail {$lease->number})");

        return Pdf::render('print.locations.avis', [
            'lease' => $lease,
            'commune' => Municipality::first(),
            'groups' => $calc->groupByTariff($months),
            'total' => LeaseCalculator::total($months),
            'period' => LeaseCalculator::periodLabel($months),
            'periodAr' => LeaseCalculator::periodLabel($months, true),
        ], "avis-non-paiement-bail-{$lease->number}.pdf");
    }
}
