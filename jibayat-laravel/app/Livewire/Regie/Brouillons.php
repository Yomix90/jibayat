<?php

namespace App\Livewire\Regie;

use Livewire\Attributes\Title;

/** Recettes en brouillon : modification, validation (une à une ou en lot) par le régisseur. */
#[Title('Brouillons & validation')]
class Brouillons extends Recettes
{
    public const ROUTE = 'regie.brouillons';

    public string $status = 'brouillon';

    public function mount(): void
    {
        $this->from = $this->from ?: '';
        $this->to = $this->to ?: '';
    }
}
