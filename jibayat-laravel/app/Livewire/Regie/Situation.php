<?php

namespace App\Livewire\Regie;

use App\Domain\Regie\Services\Ledger;
use App\Livewire\AppPage;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

/** Situation journalière : recettes du jour par rubrique et mode, versements, solde d'ouverture et de clôture. */
#[Title('Situation journalière')]
class Situation extends AppPage
{
    public const ROUTE = 'regie.situation';

    #[Url(as: 'date')]
    public string $date = '';

    public function mount(): void
    {
        $this->date = $this->date ?: today()->toDateString();
    }

    public function shift(int $days): void
    {
        $this->date = \Carbon\Carbon::parse($this->date ?: today())->addDays($days)->toDateString();
    }

    public function render(Ledger $ledger)
    {
        return view('livewire.regie.situation', ['s' => $ledger->dailySituation($this->date ?: today()->toDateString())]);
    }
}
