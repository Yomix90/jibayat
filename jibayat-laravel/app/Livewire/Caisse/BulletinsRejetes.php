<?php

namespace App\Livewire\Caisse;

use Livewire\Attributes\Title;

/** Bulletins rejetés par le régisseur : à corriger et réémettre, ou à annuler. */
#[Title('Bulletins rejetés')]
class BulletinsRejetes extends Bulletins
{
    public const ROUTE = 'caisse.rejetes';

    public string $status = 'rejete';
}
