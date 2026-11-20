<?php

namespace App\Livewire\Transport;

use App\Livewire\Modules\ModuleDashboard;
use Livewire\Attributes\Title;

#[Title('Tableau de bord — Transport')]
class TableauDeBord extends ModuleDashboard
{
    public const ROUTE = 'transport.tableau-de-bord';

    public const MODULE = 'STATIONNEMENT';
}
