<?php

namespace App\Livewire\Regie;

use Livewire\Attributes\Title;

#[Title('Remise de service')]
class PvRemise extends ProcesVerbal
{
    public const ROUTE = 'regie.pv-remise';

    public const TYPE = 'remise';
}
