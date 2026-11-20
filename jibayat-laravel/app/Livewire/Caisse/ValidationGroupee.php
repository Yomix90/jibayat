<?php

namespace App\Livewire\Caisse;

use Livewire\Attributes\Title;

/** Validation groupée : cocher plusieurs bulletins en attente et les solder sous une même quittance. */
#[Title('Validation groupée')]
class ValidationGroupee extends Bulletins
{
    public const ROUTE = 'caisse.validation-groupee';

    public bool $bulk = true;
}
