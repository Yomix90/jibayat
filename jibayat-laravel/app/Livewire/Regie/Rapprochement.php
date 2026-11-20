<?php

namespace App\Livewire\Regie;

use App\Domain\Regie\Services\ControlService;
use App\Livewire\AppPage;
use App\Livewire\Regie\Concerns\HasPeriod;
use Livewire\Attributes\Title;

/** Contrôle croisé des saisies du mois : modules ✕ régie par jour, livre de caisse, doublons, versements. */
#[Title('Contrôle croisé')]
class Rapprochement extends AppPage
{
    use HasPeriod;

    public const ROUTE = 'regie.rapprochement';

    public function render(ControlService $control)
    {
        return view('livewire.regie.rapprochement', ['c' => $control->crossCheck($this->year, $this->month)]);
    }
}
