<?php

namespace App\Livewire\Regie;

use Livewire\Attributes\Title;

#[Title('Arrêté de caisse')]
class PvArrete extends ProcesVerbal
{
    public const ROUTE = 'regie.pv-arrete';

    public const TYPE = 'arrete';
}
