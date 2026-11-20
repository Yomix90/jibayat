<?php

namespace App\Livewire\Fourriere;

use App\Domain\Fourriere\Models\ImpoundCase;
use App\Domain\Fourriere\Services\ImpoundCalculator;
use App\Domain\Fourriere\Services\ImpoundService;
use App\Livewire\AppPage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

/** Encaisser les frais de fourrière — Flask /fourriere/<id>/paiement et /payer (propriétaire + n° de BV). */
#[Title('Encaisser — Fourrière')]
class Encaisser extends AppPage
{
    public const ROUTE = 'fourriere.encaisser';

    #[Url(as: 'dossier')]
    public ?int $caseId = null;

    #[Url(as: 'q')]
    public string $search = '';

    public string $owner_name = '';

    public string $owner_cin = '';

    public string $owner_phone = '';

    public string $slip_number = '';

    public function choose(int $id): void
    {
        $this->caseId = $id;
        $c = ImpoundCase::find($id);
        $this->owner_name = (string) $c?->owner_name;
        $this->owner_cin = (string) $c?->owner_cin;
        $this->owner_phone = (string) $c?->owner_phone;
        $this->reset('slip_number');
        $this->resetValidation();
    }

    public function pay(ImpoundService $service): void
    {
        $this->authorizeWrite('bulletins.creer');
        $this->validate([
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_cin' => ['nullable', 'string', 'max:30'],
            'owner_phone' => ['nullable', 'string', 'max:40'],
            'slip_number' => ['nullable', 'string', 'max:40'],
        ], [], ['owner_name' => 'nom du propriétaire', 'slip_number' => 'n° de bulletin']);
        try {
            $d = $service->pay(ImpoundCase::findOrFail($this->caseId), auth()->user(),
                ['name' => $this->owner_name, 'cin' => $this->owner_cin ?: null, 'phone' => $this->owner_phone ?: null], $this->slip_number);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }
        $this->notify('Bulletin n° '.$d->paymentSlips()->value('slip_number').' créé — '.number_format((float) $d->total, 2, ',', ' ').' DH, en attente du régisseur.');
        $this->redirectRoute('fourriere.fiche', ['dossier' => $this->caseId], navigate: false);
    }

    public function render()
    {
        $c = $this->caseId ? ImpoundCase::where('is_active', true)->find($this->caseId) : null;
        if (! $c) {
            $s = $this->search;

            return view('livewire.fourriere.encaisser-choose', [
                'rows' => ImpoundCase::where('is_active', true)->where('status', 'en_fourriere')
                    ->when($s, fn ($q) => $q->where(fn ($w) => $w->where('registration', 'like', "%{$s}%")->orWhere('number', 'like', "%{$s}%")
                        ->orWhere('deposit_number', 'like', "%{$s}%")))
                    ->orderBy('impounded_on')->limit(150)->get()
                    ->reject(fn ($x) => ImpoundCalculator::hasPendingPayment($x))
                    ->map(fn ($x) => ['case' => $x] + ImpoundCalculator::enrich($x)),
            ]);
        }

        return view('livewire.fourriere.encaisser', [
            'case' => $c, 'info' => ImpoundCalculator::enrich($c),
            'blocked' => $c->status !== 'en_fourriere' || ImpoundCalculator::hasPendingPayment($c) || ImpoundCalculator::hasValidatedPayment($c),
            'payments' => Fiche::payments($c),
        ]);
    }
}
