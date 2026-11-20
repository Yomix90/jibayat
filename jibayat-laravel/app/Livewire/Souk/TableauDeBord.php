<?php

namespace App\Livewire\Souk;

use App\Livewire\Modules\ModuleDashboard;
use Livewire\Attributes\Title;

#[Title('Tableau de bord — Souk')]
class TableauDeBord extends ModuleDashboard
{
    public const ROUTE = 'souk.tableau-de-bord';

    public const MODULE = 'AFFERMAGE_SOUKS';
}
