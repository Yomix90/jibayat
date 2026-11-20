<?php

namespace App\Livewire\Fourriere;

use App\Livewire\Modules\ModuleDashboard;
use Livewire\Attributes\Title;

#[Title('Tableau de bord — Fourrière')]
class TableauDeBord extends ModuleDashboard
{
    public const ROUTE = 'fourriere.tableau-de-bord';

    public const MODULE = 'FOURRIERE';
}
