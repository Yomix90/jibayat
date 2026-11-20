<?php

namespace App\Livewire\Transport;

use App\Livewire\Modules\ModuleRegister;
use Livewire\Attributes\Title;

#[Title('Registre — Transport')]
class Registre extends ModuleRegister
{
    public const ROUTE = 'transport.registre';

    public const MODULE = 'STATIONNEMENT';
}
