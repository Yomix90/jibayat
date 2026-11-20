<?php

namespace App\Livewire\Regie;

use App\Domain\Regie\Services\StockService;
use App\Livewire\AppPage;
use Livewire\Attributes\Title;

/** État de stock imprimable : par valeur, réserve TGR, régie, chez les agents, consommé, en unités et en valeur. */
#[Title('État de stock')]
class EtatStock extends AppPage
{
    public const ROUTE = 'regie.etat-stock';

    public function render(StockService $service)
    {
        return view('livewire.regie.etat-stock', ['state' => $service->state()]);
    }
}
