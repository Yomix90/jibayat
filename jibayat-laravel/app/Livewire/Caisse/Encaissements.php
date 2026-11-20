<?php

namespace App\Livewire\Caisse;

use Livewire\Attributes\Title;

/** Bulletins quittancés (encaissements). */
#[Title('Encaissements')]
class Encaissements extends Bulletins
{
    public const ROUTE = 'caisse.encaissements';

    public string $status = 'paye';
}
