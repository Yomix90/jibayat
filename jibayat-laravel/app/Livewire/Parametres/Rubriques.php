<?php

namespace App\Livewire\Parametres;

use App\Models\Rubric;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;

/**
 * Rubriques fiscales (nomenclature) — Flask : /rubriques (ajout, modification, activation).
 * Le module d'une rubrique est fixé à la création (il relie la rubrique aux calculs).
 */
#[Title('Rubriques')]
class Rubriques extends SettingsPage
{
    public const ROUTE = 'parametres.rubriques';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $code = '';

    public string $label = '';

    public string $label_ar = '';

    public string $module = '';

    public string $description = '';

    public string $family = 'autres';

    public bool $manual_entry = true;

    public bool $special_tax = false;

    public function create(): void
    {
        $this->authorizeWrite();
        $this->reset('editingId', 'code', 'label', 'label_ar', 'module', 'description', 'family', 'manual_entry', 'special_tax');
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorizeWrite();
        $r = Rubric::findOrFail($id);
        $this->editingId = $r->id;
        $this->code = (string) $r->code;
        $this->label = $r->label;
        $this->label_ar = (string) $r->label_ar;
        $this->module = $r->module;
        $this->description = (string) $r->description;
        $this->family = (string) ($r->family ?: 'autres');
        $this->manual_entry = (bool) $r->manual_entry;
        $this->special_tax = (bool) $r->special_tax;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorizeWrite();
        $data = $this->validate([
            'code' => ['nullable', 'string', 'max:30'],
            'label' => ['required', 'string', 'max:255'],
            'label_ar' => ['nullable', 'string', 'max:255'],
            'module' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9_]+$/', Rule::unique('rubrics', 'module')->ignore($this->editingId)],
            'description' => ['nullable', 'string', 'max:2000'],
            'family' => ['required', Rule::in(array_keys(\App\Domain\Regie\Services\Ledger::FAMILIES))],
            'manual_entry' => ['boolean'],
            'special_tax' => ['boolean'],
        ], [], ['label' => 'libellé', 'label_ar' => 'libellé arabe', 'module' => 'code module']);

        if ($this->editingId) {
            unset($data['module']);
            Rubric::findOrFail($this->editingId)->update($data);
            $this->notify('Rubrique modifiée.');
        } else {
            Rubric::create($data + ['is_active' => true]);
            $this->notify('Rubrique ajoutée.');
        }
        $this->showForm = false;
    }

    public function toggle(int $id): void
    {
        $this->authorizeWrite();
        $r = Rubric::findOrFail($id);
        $r->update(['is_active' => ! $r->is_active]);
        $this->notify($r->is_active ? 'Rubrique activée.' : 'Rubrique désactivée.');
    }

    public function render()
    {
        return view('livewire.parametres.rubriques', [
            'rubrics' => Rubric::withCount('tariffs')->orderBy('module')->get(),
        ]);
    }
}
