<?php

namespace App\Livewire\Regie;

use App\Domain\Regie\Services\StockService;
use App\Livewire\AppPage;
use Livewire\Attributes\Title;

/** Intelligence du stock : sorties des 12 derniers mois, consommation moyenne, couverture, commande conseillée, export Excel. */
#[Title('Intelligence du stock')]
class Intelligence extends AppPage
{
    public const ROUTE = 'regie.intelligence';

    public function render(StockService $service)
    {
        return view('livewire.regie.intelligence', ['i' => $service->intelligence()]);
    }
}
