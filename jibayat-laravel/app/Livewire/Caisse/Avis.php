<?php

namespace App\Livewire\Caisse;

use App\Domain\Caisse\Services\NoticeService;
use App\Livewire\AppPage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

/** Avis de non-paiement — Flask /avis : impayés par taxe, génération des avis (lot ou individuel), lettre à partir des avis cochés. */
#[Title('Avis de non-paiement')]
class Avis extends AppPage
{
    public const ROUTE = 'caisse.avis';

    #[Url(as: 'module')]
    public string $module = '';

    #[Url(as: 'statut')]
    public string $status = '';

    #[Url(as: 'annee')]
    public string $year = '';

    /** @var list<string> */
    public array $selected = [];

    public string $letterType = 'relance';

    public function generate(NoticeService $service): void
    {
        $this->authorizeWrite('bulletins.creer');
        [$n, $batch] = $service->generate($this->module ?: null, auth()->user());
        $this->notify($n ? "$n avis générés (lot $batch)." : 'Aucun nouvel avis : tous les impayés ont déjà un avis émis.', $n ? 'success' : 'warning');
    }

    public function generateOne(int $declarationId, NoticeService $service): void
    {
        $this->authorizeWrite('bulletins.creer');
        try {
            $n = $service->generateOne($declarationId, auth()->user());
        } catch (ValidationException $e) {
            $this->notify($e->validator->errors()->first(), 'warning');

            return;
        }
        $this->notify("Avis {$n->number} créé.");
    }

    public function createLetter(NoticeService $service)
    {
        $this->authorizeWrite('bulletins.creer');
        try {
            $letter = $service->createLetter($this->module ?: null, $this->letterType, array_map('intval', $this->selected), auth()->user());
        } catch (ValidationException $e) {
            $this->notify($e->validator->errors()->first(), 'warning');

            return null;
        }
        $this->selected = [];
        session()->flash('toast', "Lettre {$letter->number} créée en brouillon ({$letter->taxpayer_count} avis).");

        return $this->redirectRoute('caisse.lettres', ['lettre' => $letter->id], navigate: false);
    }

    public function render(NoticeService $service)
    {
        return view('livewire.caisse.avis', $service->overview($this->module, $this->status, $this->year) + ['stats' => $service->stats()]);
    }
}
