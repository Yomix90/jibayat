<?php

namespace App\Livewire\Declarations;

use App\Livewire\AppPage;
use Livewire\Attributes\Title;

/** Formulaires officiels vierges des déclarations d'assiette, à imprimer et faire signer. */
#[Title('Formulaires officiels')]
class Formulaires extends AppPage
{
    public const ROUTE = 'declarations.formulaires';

    public function render()
    {
        return view('livewire.declarations.formulaires');
    }
}
