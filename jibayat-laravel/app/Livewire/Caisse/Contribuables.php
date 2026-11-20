<?php

namespace App\Livewire\Caisse;

use App\Domain\Contribuables\Services\TaxpayerService;
use App\Livewire\AppPage;
use App\Models\Taxpayer;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/** Répertoire des contribuables — Flask /contribuables : recherche, filtre par taxe, impayés, export, impression. */
#[Title('Contribuables')]
class Contribuables extends AppPage
{
    use WithPagination;

    public const ROUTE = 'caisse.contribuables';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'module')]
    public string $module = '';

    public function updated(string $name): void
    {
        if (in_array($name, ['search', 'module'], true)) {
            $this->resetPage();
        }
    }

    public function deactivate(int $id, TaxpayerService $service): void
    {
        $this->authorizeWrite('contribuables.supprimer');
        $t = Taxpayer::findOrFail($id);
        $service->deactivate($t, auth()->user());
        $this->notify("{$t->displayName()} a été retiré du répertoire (son historique est conservé).", 'warning');
    }

    public function render(TaxpayerService $service)
    {
        $list = $service->list($this->search, $this->module);
        $items = collect($list['items']);
        $page = $this->getPage();

        return view('livewire.caisse.contribuables', [
            'rows' => new LengthAwarePaginator($items->forPage($page, 25)->values(), $items->count(), 25, $page),
            'totalUnpaid' => $list['total_unpaid'],
            'debtors' => $items->filter(fn ($t) => $t->unpaid > 0)->count(),
            'withoutModule' => $items->filter(fn ($t) => ! $t->modules)->count(),
            'query' => array_filter(['q' => $this->search, 'module' => $this->module]),
        ]);
    }
}
