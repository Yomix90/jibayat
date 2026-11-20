<?php

namespace App\Livewire\Regie;

use App\Domain\Regie\Services\Ledger;
use App\Livewire\AppPage;
use App\Livewire\Regie\Concerns\HasPeriod;
use Livewire\Attributes\Title;

/** Opérations avec le Trésor (Modèle 7) : par mois, recettes, versements au comptable, accusés, soldes. */
#[Title('Opérations Trésor')]
class OperationsTresor extends AppPage
{
    use HasPeriod;

    public const ROUTE = 'regie.operations-tresor';

    public function render(Ledger $ledger)
    {
        return view('livewire.regie.operations-tresor', ['ops' => $ledger->treasuryOperations($this->year)]);
    }
}
