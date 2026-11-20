<?php

namespace App\Livewire\Boissons;

use App\Livewire\Modules\ModuleRegister;
use Livewire\Attributes\Title;

#[Title('Registre — Débits de boissons')]
class Registre extends ModuleRegister
{
    public const ROUTE = 'boissons.registre';

    public const MODULE = 'DEBITS_BOISSONS';
}
