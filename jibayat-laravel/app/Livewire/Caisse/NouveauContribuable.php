<?php

namespace App\Livewire\Caisse;

use App\Domain\Contribuables\Services\TaxpayerService;
use App\Livewire\AppPage;
use App\Livewire\Caisse\Concerns\TaxpayerForm;
use Livewire\Attributes\Title;

/** Nouveau contribuable — Flask /contribuables/ajouter (un CIN déjà connu est signalé au lieu de créer un doublon). */
#[Title('Nouveau contribuable')]
class NouveauContribuable extends AppPage
{
    use TaxpayerForm;

    public const ROUTE = 'caisse.nouveau-contribuable';

    public function mount(): void
    {
        $this->authorizeWrite('contribuables.ajouter');
        $this->fillForm();
    }

    public function save(TaxpayerService $service)
    {
        $this->authorizeWrite('contribuables.ajouter');
        if (! $data = $this->validatedForm()) {
            return null;
        }
        $t = $service->create($data, auth()->user());
        session()->flash('toast', "Contribuable {$t->number} créé.");

        return $this->redirectRoute('caisse.fiche-contribuable', ['ctb' => $t->id], navigate: false);
    }

    public function render()
    {
        return view('livewire.caisse.nouveau-contribuable');
    }
}
