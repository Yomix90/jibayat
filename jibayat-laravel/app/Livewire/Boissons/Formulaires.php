<?php

namespace App\Livewire\Boissons;

use App\Domain\Boissons\Models\BeverageOutlet;
use App\Livewire\AppPage;
use Livewire\Attributes\Title;

/**
 * Formulaires de déclaration — Flask /debits-boissons/pdf-declaration-<type> et pdf-declaration-ca :
 * création, chiffre d'affaires (modèle 6), cessation ; vierges ou pré-remplis pour un établissement.
 */
#[Title('Formulaires de déclaration — Débits de boissons')]
class Formulaires extends AppPage
{
    public const ROUTE = 'boissons.declarations';

    public string $outletId = '';

    public function render()
    {
        return view('livewire.boissons.formulaires', [
            'outlets' => BeverageOutlet::where('is_active', true)->orderBy('name')->get(['id', 'number', 'name']),
        ]);
    }
}
