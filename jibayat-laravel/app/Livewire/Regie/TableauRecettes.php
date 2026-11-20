<?php

namespace App\Livewire\Regie;

use App\Domain\Regie\Services\Ledger;
use App\Livewire\AppPage;
use App\Livewire\Regie\Concerns\HasPeriod;
use Livewire\Attributes\Title;

/** Tableau des recettes d'une année : rubriques × mois, totaux ; impression et export Excel. */
#[Title('Tableau des recettes')]
class TableauRecettes extends AppPage
{
    use HasPeriod;

    public const ROUTE = 'regie.tableau-recettes';

    public function render(Ledger $ledger)
    {
        return view('livewire.regie.tableau-recettes', ['t' => $ledger->yearTable($this->year)]);
    }
}
