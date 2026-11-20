<?php

namespace App\Livewire\Declarations;

use App\Domain\Declarations\Services\DeclarationService;
use App\Livewire\AppPage;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

/** Échéances annuelles : terrains sans déclaration annuelle (art. 47) et débits de boissons sans déclaration des recettes (art. 67-II). */
#[Title('Échéances annuelles')]
class Echeances extends AppPage
{
    public const ROUTE = 'declarations.echeances';

    #[Url(as: 'annee')]
    public int $year = 0;

    public function mount(): void
    {
        $this->year = $this->year ?: (int) now()->year;
    }

    public function render(DeclarationService $service)
    {
        return view('livewire.declarations.echeances', $service->missingAnnual($this->year) + ['today' => today()]);
    }
}
