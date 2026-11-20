<?php

namespace App\Livewire\Regie;

use App\Domain\Regie\Models\TreasuryDispatch;
use App\Domain\Regie\Services\RemittanceService;
use App\Livewire\AppPage;
use Livewire\Attributes\Title;
use Livewire\WithPagination;

/** Envois au Trésor : bordereau d'envoi des pièces (versements, états, PV…), accusé de réception du receveur. */
#[Title('Envois au Trésor')]
class Envois extends AppPage
{
    use WithPagination;

    public const ROUTE = 'regie.envois';

    public bool $showForm = false;

    public string $sentOn = '';

    public string $recipient = '';

    public string $notes = '';

    public array $chosen = [];

    public array $extra = [];

    public ?int $ackId = null;

    public string $ackOn = '';

    public string $ackName = '';

    public ?int $cancelId = null;

    public string $cancelReason = '';

    public function openForm(): void
    {
        $this->authorizeWrite('recettes.modifier');
        $this->sentOn = today()->toDateString();
        $this->recipient = RemittanceService::accountant();
        $this->notes = '';
        $this->chosen = [];
        $this->extra = [['label' => '', 'count' => 1]];
        $this->resetValidation();
        $this->showForm = true;
    }

    public function addExtra(): void
    {
        $this->extra[] = ['label' => '', 'count' => 1];
    }

    public function save(RemittanceService $service): void
    {
        $this->authorizeWrite('recettes.modifier');
        $this->validate(['sentOn' => ['required', 'date'], 'recipient' => ['required', 'string', 'max:150']]);
        $candidates = collect($service->dispatchCandidates())->keyBy(fn ($c) => $c['type'].':'.$c['ref']);
        $items = collect($this->chosen)->map(fn ($k) => $candidates->get($k))->filter()->values()->all();
        foreach ($this->extra as $e) {
            if (trim((string) $e['label']) !== '') {
                $items[] = ['type' => 'autre', 'ref' => '', 'label' => $e['label'], 'count' => (int) $e['count']];
            }
        }
        $d = $service->dispatch($this->sentOn, $this->recipient, $items, $this->notes, auth()->user());
        $this->showForm = false;
        $this->notify("Bordereau d’envoi {$d->number} enregistré.");
    }

    public function askAck(int $id): void
    {
        $this->authorizeWrite('recettes.modifier');
        $this->ackId = $id;
        $this->ackOn = today()->toDateString();
        $this->ackName = '';
        $this->resetValidation();
    }

    public function confirmAck(RemittanceService $service): void
    {
        $this->authorizeWrite('recettes.modifier');
        $this->validate(['ackOn' => ['required', 'date'], 'ackName' => ['required', 'string', 'max:150']], [], ['ackName' => 'receveur']);
        $service->acknowledgeDispatch(TreasuryDispatch::findOrFail($this->ackId), $this->ackOn, $this->ackName, auth()->user());
        $this->ackId = null;
        $this->notify('Accusé de réception enregistré.');
    }

    public function askCancel(int $id): void
    {
        $this->authorizeWrite('paiements.valider');
        $this->cancelId = $id;
        $this->cancelReason = '';
        $this->resetValidation();
    }

    public function confirmCancel(): void
    {
        $this->authorizeWrite('paiements.valider');
        $this->validate(['cancelReason' => ['required', 'string', 'min:5', 'max:255']], [], ['cancelReason' => 'motif']);
        $d = TreasuryDispatch::findOrFail($this->cancelId);
        if (! $d->isCancelled()) {
            $d->cancel(auth()->user(), $this->cancelReason);
        }
        $this->cancelId = null;
        $this->notify('Bordereau d’envoi annulé.', 'warning');
    }

    public function render(RemittanceService $service)
    {
        return view('livewire.regie.envois', [
            'rows' => TreasuryDispatch::orderByDesc('sent_on')->orderByDesc('id')->paginate(20),
            'candidates' => $this->showForm ? $service->dispatchCandidates() : [],
            'types' => RemittanceService::DISPATCH_TYPES,
        ]);
    }
}
