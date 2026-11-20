<?php

namespace App\Livewire\Regie;

use App\Domain\Regie\Services\Ledger;
use App\Livewire\AppPage;
use App\Livewire\Regie\Concerns\HasPeriod;
use Livewire\Attributes\Title;

/** État mensuel des recettes (Modèle 10) : par rubrique, report, mois, cumul. Figé à la clôture du mois. */
#[Title('État mensuel')]
class EtatMensuel extends AppPage
{
    use HasPeriod;

    public const ROUTE = 'regie.etat-mensuel';

    public function render(Ledger $ledger)
    {
        return view('livewire.regie.etat-mensuel', ['state' => $ledger->monthlyState($this->year, $this->month)]);
    }
}
