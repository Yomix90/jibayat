<?php

namespace App\Livewire\Parametres;

use App\Domain\Documents\Models\Employee;
use App\Domain\Documents\Models\Service;
use App\Domain\Documents\Models\StampValue;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

/**
 * Intervenants de la régie — Flask : /regie/config (services, employés détenteurs de carnets,
 * valeurs inactives / timbres). Un élément déjà utilisé par des carnets ne se supprime pas : on le désactive.
 */
#[Title('Intervenants')]
class Intervenants extends SettingsPage
{
    public const ROUTE = 'parametres.regie';

    #[Url(as: 'onglet')]
    public string $tab = 'services';

    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = [];

    protected function model(): string
    {
        return ['services' => Service::class, 'employes' => Employee::class, 'valeurs' => StampValue::class][$this->tab];
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['services', 'employes', 'valeurs'], true) ? $tab : 'services';
        $this->showForm = false;
    }

    public function create(): void
    {
        $this->authorizeWrite();
        $this->editingId = null;
        $this->form = match ($this->tab) {
            'services' => ['name' => ''],
            'employes' => ['registration_number' => '', 'last_name' => '', 'first_name' => '', 'service_id' => ''],
            'valeurs' => ['type' => 'timbre', 'label' => '', 'unit_value' => '', 'units_per_book' => ''],
        };
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorizeWrite();
        $item = ($this->model())::findOrFail($id);
        $this->editingId = $id;
        $this->form = collect($item->only(array_keys($this->defaults())))->map(fn ($v) => (string) $v)->all();
        $this->resetValidation();
        $this->showForm = true;
    }

    protected function defaults(): array
    {
        return match ($this->tab) {
            'services' => ['name' => ''],
            'employes' => ['registration_number' => '', 'last_name' => '', 'first_name' => '', 'service_id' => ''],
            'valeurs' => ['type' => '', 'label' => '', 'unit_value' => '', 'units_per_book' => ''],
        };
    }

    public function save(): void
    {
        $this->authorizeWrite();
        $rules = match ($this->tab) {
            'services' => ['form.name' => ['required', 'string', 'max:255']],
            'employes' => [
                'form.registration_number' => ['nullable', 'string', 'max:40'],
                'form.last_name' => ['required', 'string', 'max:255'],
                'form.first_name' => ['nullable', 'string', 'max:255'],
                'form.service_id' => ['nullable', 'exists:services,id'],
            ],
            'valeurs' => [
                'form.type' => ['required', 'string', 'max:30'],
                'form.label' => ['required', 'string', 'max:255'],
                'form.unit_value' => ['required', 'numeric', 'min:0.01'],
                'form.units_per_book' => ['required', 'integer', 'min:1'],
            ],
        };
        $data = $this->validate($rules, [], [
            'form.name' => 'nom', 'form.last_name' => 'nom', 'form.label' => 'désignation',
            'form.unit_value' => 'valeur unitaire', 'form.units_per_book' => 'unités par carnet',
        ])['form'];
        $data = array_map(fn ($v) => $v === '' ? null : $v, $data);

        if ($this->editingId) {
            ($this->model())::findOrFail($this->editingId)->update($data);
            $this->notify('Modification enregistrée.');
        } else {
            ($this->model())::create($data + ['is_active' => true]);
            $this->notify('Ajout enregistré.');
        }
        $this->showForm = false;
    }

    public function toggle(int $id): void
    {
        $this->authorizeWrite();
        $item = ($this->model())::findOrFail($id);
        $item->update(['is_active' => ! $item->is_active]);
    }

    public function delete(int $id): void
    {
        $this->authorizeWrite();
        $column = ['services' => 'service_id', 'employes' => 'employee_id', 'valeurs' => 'stamp_value_id'][$this->tab];
        $used = DB::table('stamp_books')->where($column, $id)->exists()
            || ($this->tab === 'services' && Employee::where('service_id', $id)->exists())
            || ($this->tab === 'valeurs' && DB::table('stamp_packs')->where('stamp_value_id', $id)->exists());
        if ($used) {
            $this->notify('Élément utilisé par des carnets ou des employés : désactivez-le plutôt.', 'error');

            return;
        }
        ($this->model())::findOrFail($id)->delete();
        $this->notify('Suppression effectuée.');
    }

    public function render()
    {
        return view('livewire.parametres.intervenants', [
            'services' => Service::withCount('employees')->orderBy('name')->get(),
            'employees' => Employee::with('service')->orderBy('last_name')->get(),
            'values' => StampValue::orderBy('label')->get(),
        ]);
    }
}
