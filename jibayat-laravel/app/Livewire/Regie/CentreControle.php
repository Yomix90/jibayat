<?php

namespace App\Livewire\Regie;

use App\Domain\Regie\Services\ControlService;
use App\Livewire\AppPage;
use Livewire\Attributes\Title;

/** Centre de contrôle : tout ce qui reste à corriger à la régie, du plus grave au moins grave. */
#[Title('Centre de contrôle')]
class CentreControle extends AppPage
{
    public const ROUTE = 'regie.centre-controle';

    public function render(ControlService $control)
    {
        return view('livewire.regie.centre-controle', ['issues' => $control->issues()]);
    }
}
