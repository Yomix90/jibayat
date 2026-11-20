<?php

namespace App\Livewire\Locations;

use App\Livewire\Modules\ModuleDashboard;
use Livewire\Attributes\Title;

#[Title('Tableau de bord — Locations')]
class TableauDeBord extends ModuleDashboard
{
    public const ROUTE = 'locations.tableau-de-bord';

    public const MODULE = 'LOCATION_LOCAUX';
}
