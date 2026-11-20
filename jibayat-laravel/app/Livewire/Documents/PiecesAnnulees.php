<?php

namespace App\Livewire\Documents;

use App\Domain\Documents\Services\RegisterService;
use App\Livewire\AppPage;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

/** Pièces annulées — état des déclarations et bulletins annulés (motif, auteur, date), règle des pièces immuables. */
#[Title('Pièces annulées')]
class PiecesAnnulees extends AppPage
{
    public const ROUTE = 'documents.pieces-annulees';

    #[Url(as: 'module')]
    public string $module = '';

    #[Url(as: 'q')]
    public string $search = '';

    public function render(RegisterService $service)
    {
        $rows = $service->cancelled($this->module ?: null, trim($this->search));

        return view('livewire.documents.pieces-annulees', ['rows' => $rows, 'total' => array_sum(array_map(fn ($r) => (float) $r->amount, $rows))]);
    }
}
