<?php

namespace App\Livewire\Locations;

use App\Domain\Locations\Models\Lease;
use App\Domain\Locations\Services\LeaseCalculator;
use App\Livewire\AppPage;
use Livewire\Attributes\Title;

/**
 * Avis de non-paiement des loyers : baux ayant des mois non payés, avec impression de l'avis (mise en demeure).
 */
#[Title('Avis de non-paiement — Locations')]
class Avis extends AppPage
{
    public const ROUTE = 'locations.avis';

    public int $minMonths = 1;

    public function render(LeaseCalculator $calc)
    {
        $rows = Lease::with(['taxpayer', 'shop'])->where('is_active', true)->get()
            ->map(fn ($l) => ['lease' => $l] + $calc->situation($l))
            ->filter(fn ($r) => $r['nb_non_paye'] >= max(1, $this->minMonths))
            ->sortByDesc('total_du')->values();

        return view('livewire.locations.avis', ['rows' => $rows, 'total' => $rows->sum('total_du')]);
    }
}
