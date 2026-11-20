<?php

namespace App\Livewire\Parametres;

use App\Domain\Parametres\Services\TaxOrderService;
use App\Models\Rubric;
use App\Models\TaxOrder;
use Livewire\Attributes\Title;

/**
 * Arrêtés fiscaux — Flask : /arretes-fiscaux (liste + création avec recopie des tarifs).
 */
#[Title('Arrêtés fiscaux & tarifs')]
class TaxOrders extends SettingsPage
{
    public const ROUTE = 'parametres.arretes';

    public bool $showForm = false;

    public string $title = '';

    public string $effective_date = '';

    public string $notes = '';

    public bool $copy_tariffs = true;

    /** @var array<int> */
    public array $rubric_ids = [];

    public function create(): void
    {
        $this->authorizeWrite();
        $this->reset('title', 'notes', 'rubric_ids');
        $this->copy_tariffs = true;
        $this->effective_date = now()->startOfYear()->addYear()->toDateString();
        $this->title = 'Arrêté Fiscal '.now()->addYear()->year;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(TaxOrderService $service)
    {
        $this->authorizeWrite();
        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'effective_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'rubric_ids' => ['array'],
            'rubric_ids.*' => ['integer', 'exists:rubrics,id'],
        ], [], ['effective_date' => "date d'effet", 'title' => 'titre']);

        $order = $service->create(auth()->user(), $this->effective_date, $this->title, $this->notes ?: null,
            $this->copy_tariffs, $this->rubric_ids ?: null);

        session()->flash('toast', "Arrêté {$order->number} créé avec {$order->tariffs()->count()} tarif(s), date d'effet {$order->effective_date->format('d/m/Y')}.");

        return $this->redirectRoute('parametres.arretes.show', $order, navigate: false);
    }

    public function render()
    {
        return view('livewire.parametres.tax-orders', [
            'orders' => TaxOrder::withCount('tariffs')->with('creator')->orderByDesc('effective_date')->get(),
            'rubrics' => Rubric::where('is_active', true)->orderBy('module')->get(),
        ]);
    }
}
