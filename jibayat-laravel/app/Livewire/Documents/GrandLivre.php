<?php

namespace App\Livewire\Documents;

use App\Domain\Documents\Services\RegisterService;
use App\Livewire\AppPage;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/** Grand livre des recettes — Flask /registre/global : toutes les taxes, déclarations et quittances, filtres et totaux. */
#[Title('Grand livre des recettes')]
class GrandLivre extends AppPage
{
    use WithPagination;

    public const ROUTE = 'documents.grand-livre';

    #[Url(as: 'annee')]
    public string $year = '';

    #[Url(as: 'module')]
    public string $module = '';

    #[Url(as: 'statut')]
    public string $status = 'all';

    #[Url(as: 'q')]
    public string $search = '';

    public function mount(): void
    {
        $this->year = $this->year ?: (string) now()->year;
    }

    public function updating(): void
    {
        $this->resetPage();
    }

    public function render(RegisterService $service)
    {
        $q = $service->paymentsQuery($this->year ?: null, $this->module ?: null, $this->status, trim($this->search));

        return view('livewire.documents.grand-livre', [
            'rows' => (clone $q)->orderByDesc('d.year')->orderByDesc('d.id')->select(RegisterService::paymentColumns())->paginate(50),
            'totals' => $service->paymentTotals($q),
            'years' => RegisterService::years(),
        ]);
    }
}
