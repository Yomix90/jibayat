<?php

namespace App\Livewire;

use App\Support\Navigation;
use App\Support\RecordSearch;
use Livewire\Component;

/**
 * Recherche instantanée : pages de tous les espaces visibles par l'utilisateur (titre, sous-titre, mots-clés FR/AR)
 * et données (contribuables, dossiers de chaque taxe, déclarations, bulletins, quittances).
 */
class PageSearch extends Component
{
    public string $query = '';

    public function render(Navigation $navigation, RecordSearch $records)
    {
        $ok = mb_strlen(trim($this->query)) >= 2;

        return view('livewire.page-search', [
            'results' => $ok ? $navigation->search(auth()->user(), $this->query, 6) : collect(),
            'records' => $ok && auth()->user() ? $records->search(auth()->user(), $this->query) : collect(),
        ]);
    }
}
