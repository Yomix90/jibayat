<?php

namespace App\Http\Controllers\DomainePublic;

use App\Domain\DomainePublic\Models\PublicOccupation;
use App\Domain\DomainePublic\Services\OccupationCalculator;
use App\Http\Controllers\Controller;
use App\Livewire\DomainePublic\Occupations;
use App\Models\Municipality;
use App\Support\Pdf;
use App\Support\PyMath;
use Illuminate\Http\Request;

/** Impressions ODP — Flask : /occupation-domaine/pdf et /occupation-domaine/<id>/avis-non-paiement. */
class PrintController extends Controller
{
    public function register(Request $request, OccupationCalculator $calc)
    {
        $situation = (string) $request->query('situation', '');
        $rows = Occupations::rows($calc, (string) $request->query('q', ''))
            ->filter(fn ($r) => match ($situation) {
                'impaye' => $r['nb_non_declares'] > 0, 'en_attente' => $r['nb_emises'] > 0, 'ajour' => $r['nb_impayes'] === 0,
                'expire' => $r['est_expire'], default => true,
            })->sortBy(fn ($r) => (int) $r['occupation']->number)->values();

        return Pdf::render('print.domaine-public.liste', ['rows' => $rows], 'occupations-domaine-public.pdf', 'A4-L');
    }

    /** Avis : trimestres non déclarés (Flask : toute période déjà déclarée est exclue), au montant de leur période. */
    public function notice(PublicOccupation $occupation, OccupationCalculator $calc)
    {
        $occupation->load('taxpayer');
        $quarters = array_values(array_filter($calc->dueQuarters($occupation), fn ($q) => ! $q['est_emis']));
        $quarters = array_map(fn ($q) => $q + ['montant' => $calc->computePeriod($occupation, $q['annee'], $q['trimestre'])['total']], $quarters);
        activity('pieces')->causedBy(auth()->user())->performedOn($occupation)->log("Édition d'un avis de non-paiement ODP (occupation {$occupation->number})");

        return Pdf::render('print.domaine-public.avis', [
            'occupation' => $occupation, 'commune' => Municipality::first(), 'quarters' => $quarters,
            'total' => PyMath::round(array_sum(array_column($quarters, 'montant')), 2),
            'period' => OccupationCalculator::periodLabel($quarters), 'periodAr' => OccupationCalculator::periodLabel($quarters, true),
            'number' => sprintf('%03d/%d', $occupation->id, now()->year),
        ], "avis-odp-{$occupation->number}.pdf");
    }
}
