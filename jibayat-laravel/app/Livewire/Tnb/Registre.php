<?php

namespace App\Livewire\Tnb;

use App\Livewire\Modules\ModuleRegister;
use Livewire\Attributes\Title;

#[Title('Registre TNB')]
class Registre extends ModuleRegister
{
    public const ROUTE = 'tnb.registre';

    public const MODULE = 'TNB';
}
