<?php

namespace App\Livewire\Regie;

use App\Domain\Documents\Models\StampBook;
use App\Livewire\AppPage;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * Décharges de vignettes : les carnets (ex. vignettes 2 DH en carnets de 1 000 DH) remis aux fonctionnaires.
 * L'affectation reste dans États & documents ➜ Carnets ; ici on imprime le bon de décharge par agent et par jour,
 * et on suit les carnets non encore versés.
 */
#[Title('Décharges de vignettes')]
class DechargesVignettes extends AppPage
{
    use WithPagination;

    public const ROUTE = 'regie.decharges-vignettes';

    #[Url(as: 'etat')]
    public string $status = 'affecte';

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $books = StampBook::with(['stampValue', 'employee', 'service'])->whereNotNull('employee_id')
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->orderByDesc('assigned_on')->orderBy('employee_id')->orderBy('number')->paginate(40);
        $groups = $books->getCollection()->groupBy(fn ($b) => ($b->assigned_on?->toDateString() ?? '—').'|'.$b->employee_id);

        return view('livewire.regie.decharges-vignettes', ['books' => $books, 'groups' => $groups]);
    }
}
