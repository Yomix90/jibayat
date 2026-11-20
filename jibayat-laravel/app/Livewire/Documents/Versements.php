<?php

namespace App\Livewire\Documents;

use App\Domain\Documents\Models\StampBook;
use App\Domain\Documents\Models\StampRemittance;
use App\Domain\Documents\Services\StampService;
use App\Livewire\AppPage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;

/**
 * Versements & bordereaux — Flask /regie/versements : versement des employés (carnet consommé, écart de caisse),
 * puis bordereau de versement au percepteur pour les carnets versés.
 */
#[Title('Versements & bordereaux — Régie des timbres')]
class Versements extends AppPage
{
    public const ROUTE = 'documents.versements';

    public ?int $payId = null;

    public string $amount = '';

    public string $paidOn = '';

    public string $observation = '';

    /** @var list<string> */
    public array $selected = [];

    public string $remittanceNumber = '';

    public string $remittedOn = '';

    public function mount(): void
    {
        $this->remittedOn = today()->toDateString();
    }

    public function openPayment(int $id): void
    {
        $this->authorizeWrite('paiements.valider');
        $b = StampBook::with('stampValue')->findOrFail($id);
        $this->payId = $id;
        $this->amount = (string) ((float) $b->stampValue->unit_value * (int) $b->stampValue->units_per_book);
        $this->paidOn = today()->toDateString();
        $this->observation = '';
        $this->resetValidation();
    }

    public function savePayment(StampService $service): void
    {
        $this->authorizeWrite('paiements.valider');
        $this->validate(['amount' => ['required', 'numeric', 'min:0'], 'paidOn' => ['required', 'date', 'before_or_equal:today'], 'observation' => ['nullable', 'string', 'max:500']],
            [], ['amount' => 'montant versé', 'paidOn' => 'date du versement']);
        try {
            $diff = $service->employeePayment(StampBook::findOrFail($this->payId), (float) $this->amount, $this->paidOn, $this->observation ?: null, auth()->user());
        } catch (ValidationException $e) {
            $this->notify($e->validator->errors()->first(), 'error');

            return;
        }
        $this->payId = null;
        $this->notify($diff == 0 ? 'Versement enregistré, sans écart.' : 'Versement enregistré — écart de caisse : '.($diff > 0 ? '+' : '').number_format($diff, 2, ',', ' ').' DH.', $diff == 0 ? 'success' : 'warning');
    }

    public function createRemittance(StampService $service): void
    {
        $this->authorizeWrite('paiements.valider');
        $this->validate(['selected' => ['required', 'array', 'min:1'], 'remittedOn' => ['required', 'date'], 'remittanceNumber' => ['nullable', 'string', 'max:40']],
            ['selected.required' => 'Cochez au moins un carnet.'], ['remittedOn' => 'date du versement']);
        try {
            $r = $service->createRemittance(array_map('intval', $this->selected), $this->remittedOn, $this->remittanceNumber, auth()->user());
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }
        $this->reset('selected', 'remittanceNumber');
        $this->notify("Bordereau n° {$r->number} créé — ".number_format((float) $r->total_amount, 2, ',', ' ').' DH.');
    }

    public function render()
    {
        $pending = StampBook::with(['stampValue', 'service', 'employee'])->where('status', 'consomme')->whereNull('stamp_remittance_id')->orderByDesc('employee_paid_on')->get();

        return view('livewire.documents.versements', [
            'assigned' => StampBook::with(['stampValue', 'service', 'employee'])->where('status', 'affecte')->orderBy('assigned_on')->get(),
            'pending' => $pending,
            'pendingTotal' => (float) $pending->sum('amount_paid'),
            'selectedTotal' => (float) $pending->whereIn('id', array_map('intval', $this->selected))->sum('amount_paid'),
            'remittances' => StampRemittance::withCount('books')->latest()->limit(50)->get(),
        ]);
    }
}
