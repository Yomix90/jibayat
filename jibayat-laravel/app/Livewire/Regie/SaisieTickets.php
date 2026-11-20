<?php

namespace App\Livewire\Regie;

use App\Domain\Documents\Models\Employee;
use App\Domain\Documents\Models\StampValue;
use App\Domain\Regie\Models\TicketDischarge;
use App\Domain\Regie\Services\StockService;
use App\Livewire\AppPage;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * Décharges de tickets : sortie de tickets remis à un agent (souk, marché…) par bon de décharge, retour de l'agent
 * (tickets rendus ➜ vendus, recette enregistrée), modification motivée d'une décharge, annulation.
 */
#[Title('Décharges de tickets')]
class SaisieTickets extends AppPage
{
    use WithPagination;

    public const ROUTE = 'regie.saisie-tickets';

    #[Url(as: 'etat')]
    public string $status = 'remis';

    public bool $showForm = false;

    public array $form = [];

    public ?int $settleId = null;

    public string $returned = '';

    public string $settledOn = '';

    public ?int $editId = null;

    public array $edit = [];

    public string $editReason = '';

    public ?int $cancelId = null;

    public string $cancelReason = '';

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function openForm(): void
    {
        $this->authorizeWrite('regie.modifier');
        $this->form = ['discharge_date' => today()->toDateString(), 'stamp_value_id' => (string) StampValue::where('is_active', true)->where('type', '!=', 'timbre')->value('id'),
            'employee_id' => '', 'place' => '', 'first_number' => '', 'last_number' => '', 'quantity' => '', 'notes' => ''];
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(StockService $service): void
    {
        $this->authorizeWrite('regie.modifier');
        $this->validate(['form.discharge_date' => ['required', 'date'], 'form.stamp_value_id' => ['required', 'exists:stamp_values,id'],
            'form.employee_id' => ['required', 'exists:employees,id'], 'form.first_number' => ['nullable', 'integer', 'min:0'],
            'form.last_number' => ['nullable', 'integer', 'gte:form.first_number'], 'form.quantity' => ['nullable', 'integer', 'min:1']],
            [], ['form.employee_id' => 'agent', 'form.last_number' => 'dernier n°']);
        try {
            $d = $service->discharge($this->form, auth()->user());
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw \Illuminate\Validation\ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn ($m, $k) => ['form.'.$k => $m])->all());
        }
        $this->showForm = false;
        $this->notify("Décharge {$d->number} enregistrée : imprimez le bon et faites-le signer.");
    }

    public function askSettle(int $id): void
    {
        $this->authorizeWrite('regie.modifier');
        $this->settleId = $id;
        $this->returned = '0';
        $this->settledOn = today()->toDateString();
        $this->resetValidation();
    }

    public function confirmSettle(StockService $service): void
    {
        $this->authorizeWrite('regie.modifier');
        $this->validate(['returned' => ['required', 'integer', 'min:0'], 'settledOn' => ['required', 'date']], [], ['returned' => 'tickets rendus']);
        $service->settle(TicketDischarge::findOrFail($this->settleId), (int) $this->returned, $this->settledOn, auth()->user());
        $this->settleId = null;
        $this->notify('Retour enregistré ; la recette des tickets vendus est inscrite au journal des recettes.');
    }

    public function openEdit(int $id): void
    {
        $this->authorizeWrite('regie.modifier');
        $d = TicketDischarge::findOrFail($id);
        $this->editId = $id;
        $this->edit = ['first_number' => (string) $d->first_number, 'last_number' => (string) $d->last_number, 'quantity' => (string) $d->quantity, 'returned' => (string) $d->returned];
        $this->editReason = '';
        $this->resetValidation();
    }

    public function saveEdit(StockService $service): void
    {
        $this->authorizeWrite('regie.modifier');
        $this->validate(['edit.quantity' => ['nullable', 'integer', 'min:1'], 'edit.returned' => ['required', 'integer', 'min:0'], 'editReason' => ['required', 'string', 'min:5', 'max:255']],
            [], ['editReason' => 'motif']);
        try {
            $service->correct(TicketDischarge::findOrFail($this->editId), $this->edit, $this->editReason, auth()->user());
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw \Illuminate\Validation\ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn ($m, $k) => [$k === 'reason' ? 'editReason' : 'edit.'.$k => $m])->all());
        }
        $this->editId = null;
        $this->notify('Décharge modifiée ; la modification est conservée avec son motif.');
    }

    public function askCancel(int $id): void
    {
        $this->authorizeWrite('regie.modifier');
        $this->cancelId = $id;
        $this->cancelReason = '';
        $this->resetValidation();
    }

    public function confirmCancel(): void
    {
        $this->authorizeWrite('regie.modifier');
        $this->validate(['cancelReason' => ['required', 'string', 'min:5', 'max:255']], [], ['cancelReason' => 'motif']);
        $d = TicketDischarge::findOrFail($this->cancelId);
        if ($d->status === 'solde') {
            $this->addError('cancelReason', __('Une décharge soldée ne s’annule pas : modifiez-la.'));

            return;
        }
        if (! $d->isCancelled()) {
            $d->cancel(auth()->user(), $this->cancelReason);
        }
        $this->cancelId = null;
        $this->notify('Décharge annulée ; les tickets reviennent en stock.', 'warning');
    }

    public function render()
    {
        $q = TicketDischarge::with(['value', 'employee'])
            ->when($this->status === 'annule', fn ($q) => $q->whereNotNull('cancelled_at'))
            ->when(in_array($this->status, ['remis', 'solde'], true), fn ($q) => $q->whereNull('cancelled_at')->where('status', $this->status))
            ->orderByDesc('discharge_date')->orderByDesc('id');

        return view('livewire.regie.saisie-tickets', [
            'rows' => $q->paginate(25), 'values' => StampValue::where('is_active', true)->orderBy('label')->get(),
            'employees' => Employee::where('is_active', true)->orderBy('last_name')->get(),
            'settling' => $this->settleId ? TicketDischarge::with('value')->find($this->settleId) : null,
        ]);
    }
}
