<?php

namespace App\Livewire\DomainePublic;

use App\Domain\DomainePublic\Models\PublicOccupation;
use App\Domain\DomainePublic\Services\OccupationCalculator;
use App\Livewire\AppPage;
use Livewire\Attributes\Title;

/** Avis de non-paiement — Flask /occupation-domaine/<id>/avis-non-paiement. */
#[Title('Avis de non-paiement — Domaine public')]
class Avis extends AppPage
{
    public const ROUTE = 'domaine-public.avis';

    public int $minQuarters = 1;

    public function render(OccupationCalculator $calc)
    {
        $rows = PublicOccupation::with('taxpayer')->where('is_active', true)->orderBy('number')->get()
            ->map(fn ($o) => ['occupation' => $o] + $calc->situation($o))
            ->filter(fn ($r) => $r['nb_non_declares'] >= max(1, $this->minQuarters))
            ->sortByDesc('montant_non_declare')->values();

        return view('livewire.domaine-public.avis', ['rows' => $rows, 'total' => $rows->sum('montant_non_declare')]);
    }
}
