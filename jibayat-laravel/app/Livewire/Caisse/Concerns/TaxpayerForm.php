<?php

namespace App\Livewire\Caisse\Concerns;

use App\Domain\Contribuables\Services\TaxpayerService;
use App\Models\Taxpayer;

/** Formulaire contribuable commun (Flask ajouter_contribuable / modifier_contribuable). */
trait TaxpayerForm
{
    public array $form = [];

    public const FIELDS = ['person_type', 'last_name', 'first_name', 'last_name_ar', 'first_name_ar', 'company_name', 'company_name_ar',
        'cin', 'ice', 'rc', 'address', 'address_ar', 'city', 'postal_code', 'phone', 'email'];

    protected function fillForm(?Taxpayer $t = null): void
    {
        $this->form = collect(self::FIELDS)->mapWithKeys(fn ($f) => [$f => (string) ($t?->{$f} ?? '')])->all();
        $this->form['person_type'] = $t?->person_type ?: 'physique';
        $this->resetValidation();
    }

    protected function validatedForm(?int $exceptId = null): array
    {
        $moral = ($this->form['person_type'] ?? '') === 'morale';
        $data = $this->validate([
            'form.person_type' => ['required', 'in:physique,morale'],
            'form.last_name' => [$moral ? 'nullable' : 'required_without:form.last_name_ar', 'nullable', 'string', 'max:255'],
            'form.first_name' => ['nullable', 'string', 'max:255'], 'form.last_name_ar' => ['nullable', 'string', 'max:255'], 'form.first_name_ar' => ['nullable', 'string', 'max:255'],
            'form.company_name' => [$moral ? 'required_without:form.company_name_ar' : 'nullable', 'nullable', 'string', 'max:255'],
            'form.company_name_ar' => ['nullable', 'string', 'max:255'],
            'form.cin' => ['nullable', 'string', 'max:30'], 'form.ice' => ['nullable', 'string', 'max:30'], 'form.rc' => ['nullable', 'string', 'max:30'],
            'form.address' => ['nullable', 'string', 'max:255'], 'form.address_ar' => ['nullable', 'string', 'max:255'], 'form.city' => ['nullable', 'string', 'max:100'],
            'form.postal_code' => ['nullable', 'string', 'max:20'], 'form.phone' => ['nullable', 'string', 'max:40'], 'form.email' => ['nullable', 'email', 'max:255'],
        ], ['form.last_name.required_without' => 'Le nom (français ou arabe) est obligatoire.', 'form.company_name.required_without' => 'La raison sociale est obligatoire.'],
            ['form.cin' => 'CIN', 'form.email' => 'e-mail'])['form'];
        if ($dup = app(TaxpayerService::class)->findByCin($data['cin'] ?? null, $exceptId)) {
            $this->addError('form.cin', "Ce CIN est déjà celui de {$dup->displayName()} ({$dup->number}).");

            return [];
        }

        return $data;
    }
}
