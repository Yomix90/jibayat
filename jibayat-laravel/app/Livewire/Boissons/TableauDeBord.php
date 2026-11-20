<?php

namespace App\Livewire\Boissons;

use App\Livewire\Modules\ModuleDashboard;
use Livewire\Attributes\Title;

#[Title('Tableau de bord — Débits de boissons')]
class TableauDeBord extends ModuleDashboard
{
    public const ROUTE = 'boissons.tableau-de-bord';

    public const MODULE = 'DEBITS_BOISSONS';
}
