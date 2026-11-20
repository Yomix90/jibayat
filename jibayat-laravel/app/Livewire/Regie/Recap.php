<?php

namespace App\Livewire\Regie;

use App\Domain\Regie\Services\Ledger;
use App\Livewire\AppPage;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

/** Récapitulatif comptable d'une période : par famille, rubrique et mode de paiement (virements du Ministère compris). */
#[Title('Récapitulatif comptable')]
class Recap extends AppPage
{
    public const ROUTE = 'regie.recap';

    #[Url(as: 'du')]
    public string $from = '';

    #[Url(as: 'au')]
    public string $to = '';

    public function mount(): void
    {
        $this->from = $this->from ?: now()->startOfMonth()->toDateString();
        $this->to = $this->to ?: now()->endOfMonth()->toDateString();
    }

    public function render(Ledger $ledger)
    {
        return view('livewire.regie.recap', ['r' => $ledger->recap($this->from ?: today()->toDateString(), $this->to ?: today()->toDateString())]);
    }
}
