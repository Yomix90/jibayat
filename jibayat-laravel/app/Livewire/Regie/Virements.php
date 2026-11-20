<?php

namespace App\Livewire\Regie;

use App\Domain\Regie\Models\CashEntry;
use App\Domain\Regie\Services\Ledger;
use App\Livewire\AppPage;
use App\Livewire\Regie\Concerns\HasPeriod;
use Livewire\Attributes\Title;

/**
 * Recettes réglées par virement du Ministère : elles ne passent pas par la caisse (exclues du livre de caisse
 * et du Modèle 10) ; chacune a son autorisation de virement imprimable.
 */
#[Title('Autorisations de virement')]
class Virements extends AppPage
{
    use HasPeriod;

    public const ROUTE = 'regie.virements';

    public function render()
    {
        $rows = CashEntry::with('rubric')->where('payment_mode', Ledger::MINISTRY)->whereYear('entry_date', $this->year)
            ->orderByDesc('entry_date')->orderByDesc('id')->get();

        return view('livewire.regie.virements', ['rows' => $rows]);
    }
}
