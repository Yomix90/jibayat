<?php

namespace App\Livewire\Locations;

use App\Livewire\Modules\ModuleRegister;
use Livewire\Attributes\Title;

#[Title('Registre des loyers')]
class Registre extends ModuleRegister
{
    public const ROUTE = 'locations.registre';

    public const MODULE = 'LOCATION_LOCAUX';
}
