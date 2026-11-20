<?php

namespace App\Livewire\Aide;

use App\Livewire\AppPage;
use App\Support\Navigation;
use Livewire\Attributes\Title;

/** Guide d'utilisation — Flask /guide : démarches pas à pas et plan de chaque espace (pages accessibles à l'utilisateur). */
#[Title("Guide d'utilisation")]
class Guide extends AppPage
{
    public const ROUTE = 'parametres.guide';

    public function render(Navigation $nav)
    {
        return view('livewire.aide.guide', ['pages' => $nav->pagesForUser(auth()->user())->groupBy('space_title')]);
    }
}
