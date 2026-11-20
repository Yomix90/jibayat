<?php

namespace App\Livewire\Parametres;

use App\Models\Municipality;
use Livewire\Attributes\Title;

/**
 * Informations de la commune (en-têtes bilingues des documents) — Flask : /commune/modifier.
 */
#[Title('Commune')]
class Commune extends SettingsPage
{
    public const ROUTE = 'parametres.commune';

    public array $form = [];

    public const FIELDS = ['name', 'name_ar', 'code', 'president', 'president_ar', 'province', 'province_ar', 'region', 'region_ar'];

    public function mount(): void
    {
        $m = Municipality::first();
        $this->form = collect(self::FIELDS)->mapWithKeys(fn ($f) => [$f => (string) ($m?->{$f} ?? '')])->all();
    }

    public function save(): void
    {
        $this->authorizeWrite();
        $rules = collect(self::FIELDS)->mapWithKeys(fn ($f) => ["form.$f" => ['nullable', 'string', 'max:255']])->all();
        $rules['form.name'] = ['required', 'string', 'max:255'];
        $rules['form.code'] = ['nullable', 'string', 'max:30'];
        $data = $this->validate($rules, [], ['form.name' => 'nom de la commune'])['form'];
        $data = array_map(fn ($v) => $v === '' ? null : $v, $data);

        $m = Municipality::first();
        $m ? $m->update($data) : Municipality::create($data + ['is_active' => true]);
        $this->notify('Informations de la commune enregistrées.');
    }

    public function render()
    {
        return view('livewire.parametres.commune');
    }
}
