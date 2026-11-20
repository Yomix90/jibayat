<?php

namespace App\Livewire\Souk;

use App\Livewire\Modules\ModuleRegister;
use Livewire\Attributes\Title;

#[Title('Registre — Souk')]
class Registre extends ModuleRegister
{
    public const ROUTE = 'souk.registre';

    public const MODULE = 'AFFERMAGE_SOUKS';
}
