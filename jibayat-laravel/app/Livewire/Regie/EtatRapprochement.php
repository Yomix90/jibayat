<?php

namespace App\Livewire\Regie;

use App\Domain\Regie\Services\ControlService;
use App\Livewire\AppPage;
use App\Livewire\Regie\Concerns\HasPeriod;
use Livewire\Attributes\Title;

/** État de rapprochement régie / comptable : recettes du mois (Modèle 10) ✕ relevé TGR importé, par code budgétaire. */
#[Title('État de rapprochement')]
class EtatRapprochement extends AppPage
{
    use HasPeriod;

    public const ROUTE = 'regie.etat-rapprochement';

    public function render(ControlService $control)
    {
        return view('livewire.regie.etat-rapprochement', ['r' => $control->reconciliation($this->year, $this->month)]);
    }
}
