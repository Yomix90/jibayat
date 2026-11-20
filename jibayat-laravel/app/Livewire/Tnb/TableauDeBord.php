<?php

namespace App\Livewire\Tnb;

use App\Livewire\Modules\ModuleDashboard;
use Livewire\Attributes\Title;

#[Title('Tableau de bord — TNB')]
class TableauDeBord extends ModuleDashboard
{
    public const ROUTE = 'tnb.tableau-de-bord';

    public const MODULE = 'TNB';
}
