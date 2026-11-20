<?php

namespace App\Livewire\Fourriere;

use App\Domain\Fourriere\Models\ImpoundCase;
use App\Domain\Fourriere\Services\ImpoundCalculator;
use App\Domain\Fourriere\Services\ImpoundService;
use App\Livewire\AppPage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;

/** Sortie des véhicules — Flask /fourriere/<id>/sortie : véhicules payés (validés par le régisseur) à restituer. */
#[Title('Sortie du véhicule — Fourrière')]
class Sortie extends AppPage
{
    public const ROUTE = 'fourriere.sortie';

    public function release(int $id, ImpoundService $service): void
    {
        $this->authorizeWrite('fourriere.modifier');
        $c = ImpoundCase::findOrFail($id);
        try {
            $service->release($c, auth()->user());
        } catch (ValidationException $e) {
            $this->notify($e->validator->errors()->first(), 'error');

            return;
        }
        $this->notify("Sortie validée — {$c->registration} restitué.");
    }

    public function render()
    {
        $rows = ImpoundCase::where('is_active', true)->where('status', '!=', 'sortie')->orderBy('impounded_on')->get()
            ->filter(fn ($c) => $c->status === 'en_attente_sortie' || ImpoundCalculator::hasValidatedPayment($c))
            ->map(fn ($c) => ['case' => $c] + ImpoundCalculator::enrich($c));

        return view('livewire.fourriere.sortie', ['rows' => $rows]);
    }
}
