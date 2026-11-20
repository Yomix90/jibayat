<?php

namespace App\Livewire\Transport;

use App\Domain\Transport\Models\Vehicle;
use App\Domain\Transport\Services\TransportCalculator;
use App\Livewire\AppPage;
use Livewire\Attributes\Title;

/** Avis de non-paiement — Flask /stationnement/<id>/avis-non-paiement. */
#[Title('Avis de non-paiement — Transport')]
class Avis extends AppPage
{
    public const ROUTE = 'transport.avis';

    public int $minQuarters = 1;

    public function render(TransportCalculator $calc)
    {
        $rows = Vehicle::with('taxpayer')->where('is_active', true)->orderBy('registration')->get()
            ->map(fn ($v) => ['vehicle' => $v, 'quarters' => $calc->unpaidQuarters($v)] + $calc->situation($v))
            ->filter(fn ($r) => count($r['quarters']) >= max(1, $this->minQuarters))
            ->sortByDesc('montant_non_declare')->values();

        return view('livewire.transport.avis', ['rows' => $rows, 'total' => $rows->sum('montant_non_declare')]);
    }
}
