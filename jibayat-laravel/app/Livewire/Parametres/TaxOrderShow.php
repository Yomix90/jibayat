<?php

namespace App\Livewire\Parametres;

use App\Domain\Parametres\Services\TaxOrderService;
use App\Models\Rubric;
use App\Models\Tariff;
use App\Models\TaxOrder;
use InvalidArgumentException;
use Livewire\Attributes\Title;

/**
 * Détail d'un arrêté fiscal — Flask : /arretes-fiscaux/<id> (tarifs par rubrique, ajout de rubrique,
 * tarifs types, vider, supprimer un module, supprimer l'arrêté avec motif).
 */
#[Title('Arrêté fiscal')]
class TaxOrderShow extends SettingsPage
{
    public const ROUTE = 'parametres.arretes';

    public TaxOrder $taxOrder;

    // Formulaire tarif
    public bool $showTariffForm = false;

    public ?int $tariffId = null;

    public ?int $rubric_id = null;

    public string $label = '';

    public string $value = '';

    public string $unit = 'DH';

    public string $start_date = '';

    public string $end_date = '';

    public string $surface_min = '';

    public string $surface_max = '';

    // Ajout de rubrique
    public bool $showRubricForm = false;

    public ?int $add_rubric_id = null;

    public bool $add_copy = true;

    // Suppression de l'arrêté
    public bool $showDeleteForm = false;

    public string $delete_reason = '';

    public function mount(TaxOrder $taxOrder): void
    {
        $this->taxOrder = $taxOrder;
    }

    public function newTariff(int $rubricId): void
    {
        $this->authorizeWrite();
        $this->reset('tariffId', 'label', 'value', 'end_date', 'surface_min', 'surface_max');
        $this->rubric_id = $rubricId;
        $this->unit = 'DH';
        $this->start_date = $this->taxOrder->effective_date->toDateString();
        $this->resetValidation();
        $this->showTariffForm = true;
    }

    public function editTariff(int $id): void
    {
        $this->authorizeWrite();
        $t = $this->taxOrder->tariffs()->findOrFail($id);
        $this->tariffId = $t->id;
        $this->rubric_id = $t->rubric_id;
        $this->label = $t->label;
        $this->value = rtrim(rtrim((string) $t->value, '0'), '.');
        $this->unit = $t->unit;
        $this->start_date = $t->start_date->toDateString();
        $this->end_date = $t->end_date?->toDateString() ?? '';
        $this->surface_min = (float) $t->surface_min ? (string) (float) $t->surface_min : '';
        $this->surface_max = $t->surface_max !== null ? (string) (float) $t->surface_max : '';
        $this->resetValidation();
        $this->showTariffForm = true;
    }

    public function saveTariff(TaxOrderService $service): void
    {
        $this->authorizeWrite();
        $data = $this->validate([
            'rubric_id' => ['required', 'exists:rubrics,id'],
            'label' => [$this->tariffId ? 'nullable' : 'required', 'string', 'max:255'],
            'value' => ['required', 'numeric', 'min:0'],
            'unit' => ['required', 'string', 'max:30'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'surface_min' => ['nullable', 'numeric', 'min:0'],
            'surface_max' => ['nullable', 'numeric', 'gte:surface_min'],
        ], [], ['label' => 'libellé', 'value' => 'valeur', 'unit' => 'unité', 'start_date' => 'date de début', 'end_date' => 'date de fin']);

        if ($this->tariffId) {
            $service->updateTariff($this->taxOrder->tariffs()->findOrFail($this->tariffId), $data);
            $this->notify('Tarif modifié.');
        } else {
            $service->addTariff($this->taxOrder, Rubric::findOrFail($this->rubric_id), $data);
            $this->notify("Tarif « {$this->label} » ajouté.");
        }
        $this->showTariffForm = false;
    }

    public function deleteTariff(int $id, TaxOrderService $service): void
    {
        $this->authorizeWrite();
        $service->deleteTariffs([$this->taxOrder->tariffs()->findOrFail($id)->id]);
        $this->notify('Tarif supprimé.');
    }

    public function addRubric(TaxOrderService $service): void
    {
        $this->authorizeWrite();
        $this->validate(['add_rubric_id' => ['required', 'exists:rubrics,id']], [], ['add_rubric_id' => 'rubrique']);
        try {
            $n = $service->addRubric($this->taxOrder, Rubric::findOrFail($this->add_rubric_id), $this->add_copy);
            $this->notify("Rubrique ajoutée avec {$n} tarif(s).");
            $this->showRubricForm = false;
        } catch (InvalidArgumentException $e) {
            $this->addError('add_rubric_id', $e->getMessage());
        }
    }

    public function loadDefaults(TaxOrderService $service): void
    {
        $this->authorizeWrite();
        $n = $service->loadDefaults($this->taxOrder);
        $this->notify("{$n} tarif(s) types ajouté(s).");
    }

    public function removeModule(string $module, TaxOrderService $service): void
    {
        $this->authorizeWrite();
        $n = $service->removeModule($this->taxOrder, $module);
        $this->notify("{$n} tarif(s) du module {$module} supprimé(s).");
    }

    public function clearTariffs(TaxOrderService $service): void
    {
        $this->authorizeWrite();
        $n = $service->clearTariffs($this->taxOrder);
        $this->notify("{$n} tarif(s) supprimé(s).");
    }

    public function deleteOrder(TaxOrderService $service)
    {
        $this->authorizeWrite();
        $this->validate(['delete_reason' => ['required', 'string', 'min:5', 'max:255']], [], ['delete_reason' => 'motif']);
        $number = $this->taxOrder->number;
        $n = $service->delete($this->taxOrder, auth()->user(), $this->delete_reason);
        session()->flash('toast', "Arrêté {$number} supprimé ({$n} tarif(s)).");

        return $this->redirectRoute('parametres.arretes', navigate: false);
    }

    public function render()
    {
        $tariffs = $this->taxOrder->tariffs()->with('rubric')->orderBy('label')->get();
        $groups = $tariffs->groupBy(fn (Tariff $t) => $t->rubric->module)->sortKeys();
        $present = $tariffs->pluck('rubric_id')->unique();

        return view('livewire.parametres.tax-order-show', [
            'groups' => $groups,
            'missingRubrics' => Rubric::where('is_active', true)->whereNotIn('id', $present)->orderBy('module')->get(),
            'history' => Tariff::with(['rubric', 'taxOrder'])->where(fn ($q) => $q->where('tax_order_id', '!=', $this->taxOrder->id)->orWhereNull('tax_order_id'))
                ->orderByDesc('start_date')->limit(100)->get(),
        ]);
    }
}
