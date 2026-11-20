<?php

namespace App\Livewire\Fourriere;

use App\Livewire\Modules\ModuleRegister;
use Livewire\Attributes\Title;

#[Title('Registre — Fourrière')]
class Registre extends ModuleRegister
{
    public const ROUTE = 'fourriere.registre';

    public const MODULE = 'FOURRIERE';
}
