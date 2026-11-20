<?php

namespace App\Http\Controllers\Boissons;

use App\Domain\Boissons\Models\BeverageOutlet;
use App\Domain\Boissons\Services\BeverageCalculator;
use App\Http\Controllers\Controller;
use App\Livewire\Boissons\DeclarationAnnuelle;
use App\Livewire\Boissons\Etablissements;
use App\Models\Municipality;
use App\Support\Pdf;
use Illuminate\Http\Request;

/**
 * Impressions TDB — Flask : /debits-boissons/pdf, avis-non-paiement, declaration-annuelle?pdf=1,
 * pdf-declaration-ca, pdf-declaration-creation / cessation.
 */
class PrintController extends Controller
{
    public function register(Request $request, BeverageCalculator $calc)
    {
        $situation = (string) $request->query('situation', '');
        $rows = Etablissements::rows($calc, (string) $request->query('q', ''), (string) $request->query('statut', ''))
            ->filter(fn ($r) => match ($situation) {
                'impaye' => $r['nb_non_declares'] > 0, 'en_attente' => $r['nb_emises'] > 0, 'ajour' => $r['nb_non_paye'] === 0, default => true,
            })->sortBy(fn ($r) => (int) $r['outlet']->number)->values();

        return Pdf::render('print.boissons.liste', ['rows' => $rows], 'debits-de-boissons.pdf', 'A4-L');
    }

    /** Avis de non-paiement : mise en demeure de déclarer le CA et de payer (Flask tdb_avis). */
    public function notice(BeverageOutlet $outlet, BeverageCalculator $calc)
    {
        $outlet->load('taxpayer');
        $quarters = $calc->unpaidQuarters($outlet);
        activity('pieces')->causedBy(auth()->user())->performedOn($outlet)->log("Édition d'un avis de non-paiement TDB ({$outlet->name})");

        return Pdf::render('print.boissons.avis', [
            'outlet' => $outlet, 'commune' => Municipality::first(), 'quarters' => $quarters, 'rate' => $calc->rateForOutlet($outlet),
            'period' => BeverageCalculator::periodLabel($quarters), 'periodAr' => BeverageCalculator::periodLabel($quarters, true),
            'number' => sprintf('%03d/%d', $outlet->id, now()->year),
        ], "avis-tdb-{$outlet->number}.pdf");
    }

    public function annual(Request $request, BeverageOutlet $outlet, BeverageCalculator $calc)
    {
        $year = (int) $request->query('annee', now()->year - 1);
        $outlet->load('taxpayer');

        return Pdf::render('print.boissons.annuelle', ['outlet' => $outlet, 'year' => $year, 'rate' => $calc->rateForOutlet($outlet)]
            + DeclarationAnnuelle::summary($outlet, $year), "declaration-annuelle-{$outlet->number}-{$year}.pdf");
    }

    /** Formulaires : création, chiffre d'affaires (modèle 6), cessation — vierges ou pré-remplis. */
    public function form(Request $request, string $type, BeverageCalculator $calc)
    {
        abort_unless(in_array($type, ['creation', 'ca', 'cessation'], true), 404);
        $outlet = $request->query('etablissement') ? BeverageOutlet::with('taxpayer')->findOrFail($request->query('etablissement')) : null;
        abort_if($type === 'ca' && ! $outlet, 404);

        $quarters = [];
        if ($type === 'ca') {
            foreach (array_filter(explode(',', (string) $request->query('trims', ''))) as $k) {
                if (preg_match('/^(\d{4})_([1-4])$/', trim($k), $m)) {
                    $quarters[] = ['annee' => (int) $m[1], 'trimestre' => (int) $m[2]];
                }
            }
            $quarters = $quarters ?: $calc->unpaidQuarters($outlet);
        }

        return Pdf::render('print.boissons.formulaire', [
            'type' => $type, 'outlet' => $outlet, 'quarters' => $quarters, 'commune' => Municipality::first(),
            'rate' => $outlet ? $calc->rateForOutlet($outlet) : null,
            'number' => $outlet ? sprintf('TDB-%s-%d-%04d', strtoupper($type), now()->year, $outlet->id) : null,
        ], "formulaire-tdb-{$type}.pdf");
    }
}
