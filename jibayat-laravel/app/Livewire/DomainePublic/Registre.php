<?php

namespace App\Livewire\DomainePublic;

use App\Livewire\Modules\ModuleRegister;
use Livewire\Attributes\Title;

#[Title('Registre — Domaine public')]
class Registre extends ModuleRegister
{
    public const ROUTE = 'domaine-public.registre';

    public const MODULE = 'OCCUPATION_DOMAINE';
}
