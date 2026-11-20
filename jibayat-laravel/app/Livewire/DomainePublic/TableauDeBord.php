<?php

namespace App\Livewire\DomainePublic;

use App\Livewire\Modules\ModuleDashboard;
use Livewire\Attributes\Title;

#[Title('Tableau de bord — Domaine public')]
class TableauDeBord extends ModuleDashboard
{
    public const ROUTE = 'domaine-public.tableau-de-bord';

    public const MODULE = 'OCCUPATION_DOMAINE';
}
