<?php

namespace App\Http\Controllers\Transport;

use App\Domain\Boissons\Services\BeverageCalculator;
use App\Domain\Transport\Models\Vehicle;
use App\Domain\Transport\Services\TransportCalculator;
use App\Http\Controllers\Controller;
use App\Livewire\Transport\Vehicules;
use App\Models\Municipality;
use App\Support\Pdf;
use App\Support\PyMath;
use Illuminate\Http\Request;

/** Impressions transport — Flask : /stationnement/pdf et /stationnement/<id>/avis-non-paiement. */
class PrintController extends Controller
{
    public function register(Request $request, TransportCalculator $calc)
    {
        $situation = (string) $request->query('situation', '');
        $rows = Vehicules::rows($calc, (string) $request->query('q', ''))
            ->filter(fn ($r) => match ($situation) {
                'impaye' => $r['nb_non_declares'] > 0, 'en_attente' => $r['nb_emises'] > 0, 'ajour' => $r['nb_non_paye'] === 0, default => true,
            })->sortBy(fn ($r) => (int) $r['vehicle']->number)->values();

        return Pdf::render('print.transport.liste', ['rows' => $rows], 'vehicules-transport.pdf', 'A4-L');
    }

    /**
     * Avis : trimestres impayés au tarif de l'arrêté de chaque trimestre, sans pénalités (règle confirmée).
     * Flask divisait ici les tarifs par 4 et ajoutait 10 % de pénalité : écart corrigé.
     */
    public function notice(Vehicle $vehicle, TransportCalculator $calc)
    {
        $vehicle->load('taxpayer');
        $quarters = $calc->unpaidQuarters($vehicle);
        $lines = array_map(function ($q) use ($calc, $vehicle) {
            [$sta, $tpv] = $calc->amounts($vehicle, $q['annee'], $q['trimestre']);

            return $q + ['calc' => $calc->computeQuarter($sta, $tpv, $q['annee'], $q['trimestre'], today()->toDateString())];
        }, $quarters);
        activity('pieces')->causedBy(auth()->user())->performedOn($vehicle)->log("Édition d'un avis de non-paiement transport ({$vehicle->registration})");

        return Pdf::render('print.transport.avis', [
            'vehicle' => $vehicle, 'commune' => Municipality::first(), 'lines' => $lines,
            'total' => PyMath::round(array_sum(array_map(fn ($l) => $l['calc']['total'], $lines)), 2),
            'period' => BeverageCalculator::periodLabel($quarters), 'periodAr' => BeverageCalculator::periodLabel($quarters, true),
            'number' => sprintf('%03d/%d', $vehicle->id, now()->year),
        ], "avis-transport-{$vehicle->number}.pdf");
    }
}
