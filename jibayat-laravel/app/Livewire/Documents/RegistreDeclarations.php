<?php

namespace App\Livewire\Documents;

use App\Domain\Documents\Services\RegisterService;
use App\Livewire\AppPage;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/** Registre des déclarations — Flask /registre/declarations_global : bases, principal, pénalités, total. */
#[Title('Registre des déclarations')]
class RegistreDeclarations extends AppPage
{
    use WithPagination;

    public const ROUTE = 'documents.declarations';

    #[Url(as: 'annee')]
    public string $year = '';

    #[Url(as: 'module')]
    public string $module = '';

    #[Url(as: 'q')]
    public string $search = '';

    public function updating(): void
    {
        $this->resetPage();
    }

    public function render(RegisterService $service)
    {
        $q = $service->declarationsQuery($this->year ?: null, $this->module ?: null, trim($this->search));

        return view('livewire.documents.registre-declarations', [
            'rows' => (clone $q)->orderByDesc('d.year')->orderByDesc('d.declared_on')->orderByDesc('d.id')
                ->select(['d.id', 'd.number', 'd.module', 'd.year', 'd.quarter', 'd.base_amount', 'd.rate', 'd.principal',
                    'd.late_penalty', 'd.surcharge', 'd.non_filing_fine', 'd.total', 'd.status', 'd.declared_on',
                    't.last_name', 't.first_name', 't.company_name', 't.cin'])->paginate(50),
            'totals' => $service->declarationTotals($q),
            'years' => RegisterService::years(),
        ]);
    }
}
