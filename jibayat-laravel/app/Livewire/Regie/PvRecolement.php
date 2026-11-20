<?php

namespace App\Livewire\Regie;

use Livewire\Attributes\Title;

#[Title('Récolement des tickets et vignettes')]
class PvRecolement extends ProcesVerbal
{
    public const ROUTE = 'regie.pv-recolement';

    public const TYPE = 'recolement';
}
