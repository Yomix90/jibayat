<?php

namespace App\Livewire\Souk;

use App\Domain\Souk\Models\SoukContract;
use App\Domain\Souk\Services\SoukCalculator;
use App\Livewire\AppPage;
use Livewire\Attributes\Title;

/**
 * Avis de non-paiement — Flask /souks/<id>/avis-non-paiement. Écart : Flask « créer un avis » enregistrait aussi une
 * déclaration « émise » sans bulletin, qui bloquait ensuite ces mois ; ici l'avis est une pièce imprimée (journalisée).
 */
#[Title('Avis de non-paiement — Souk')]
class Avis extends AppPage
{
    public const ROUTE = 'souk.avis';

    public int $minMonths = 1;

    public function render(SoukCalculator $calc)
    {
        $rows = SoukContract::with('taxpayer')->where('is_active', true)->orderBy('souk_name')->get()
            ->map(fn ($c) => ['contract' => $c] + $calc->situation($c))
            ->filter(fn ($r) => $r['nb_mois_impaye'] >= max(1, $this->minMonths))
            ->sortByDesc('total_impaye')->values();

        return view('livewire.souk.avis', ['rows' => $rows, 'total' => $rows->sum('total_impaye')]);
    }
}
