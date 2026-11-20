<?php

namespace App\Livewire\Boissons;

use App\Domain\Boissons\Models\BeverageOutlet;
use App\Domain\Boissons\Services\BeverageCalculator;
use App\Livewire\AppPage;
use Livewire\Attributes\Title;

/**
 * Avis de non-paiement (mise en demeure de déclarer) — Flask /debits-boissons/<id>/avis-non-paiement.
 */
#[Title('Avis de non-paiement — Débits de boissons')]
class Avis extends AppPage
{
    public const ROUTE = 'boissons.avis';

    public int $minQuarters = 1;

    public function render(BeverageCalculator $calc)
    {
        $rows = BeverageOutlet::with('taxpayer')->where('is_active', true)->orderBy('name')->get()
            ->map(fn ($o) => ['outlet' => $o, 'quarters' => $calc->unpaidQuarters($o)])
            ->filter(fn ($r) => count($r['quarters']) >= max(1, $this->minQuarters))
            ->sortByDesc(fn ($r) => count($r['quarters']))->values();

        return view('livewire.boissons.avis', ['rows' => $rows]);
    }
}
