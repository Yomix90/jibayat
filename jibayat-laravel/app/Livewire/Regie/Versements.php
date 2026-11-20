<?php

namespace App\Livewire\Regie;

use App\Domain\Regie\Models\CashRemittance;
use App\Domain\Regie\Services\Ledger;
use App\Domain\Regie\Services\RemittanceService;
use App\Livewire\AppPage;
use Livewire\Attributes\Title;
use Livewire\WithPagination;

/**
 * Bordereaux de versement au comptable de rattachement : préparation sur une période (détail par rubrique),
 * contrôle du solde de caisse, impression, accusé du comptable, annulation motivée.
 * (Les versements des carnets de timbres au percepteur restent dans États & documents ➜ Versements.)
 */
#[Title('Bordereaux de versement')]
class Versements extends AppPage
{
    use WithPagination;

    public const ROUTE = 'regie.versements';

    public bool $showForm = false;

    public array $form = [];

    public ?int $ackId = null;

    public string $ackOn = '';

    public string $ackRef = '';

    public ?int $cancelId = null;

    public string $cancelReason = '';

    public function openForm(Ledger $ledger): void
    {
        $this->authorizeWrite('recettes.modifier');
        $last = CashRemittance::active()->max('period_to');
        $from = $last ? \Carbon\Carbon::parse($last)->addDay()->toDateString() : now()->startOfMonth()->toDateString();
        $this->form = ['remitted_on' => today()->toDateString(), 'period_from' => min($from, today()->toDateString()), 'period_to' => today()->toDateString(),
            'payment_mode' => 'especes', 'amount' => '', 'accountant' => RemittanceService::accountant(), 'bank_reference' => '', 'notes' => ''];
        $this->fillAmount();
        $this->resetValidation();
        $this->showForm = true;
    }

    public function updatedFormPeriodFrom(): void
    {
        $this->fillAmount();
    }

    public function updatedFormPeriodTo(): void
    {
        $this->fillAmount();
    }

    protected function fillAmount(): void
    {
        if (! empty($this->form['period_from']) && ! empty($this->form['period_to'])) {
            $p = app(RemittanceService::class)->proposal($this->form['period_from'], $this->form['period_to']);
            $this->form['amount'] = (string) max(0, min($p['total'] - $p['already'], $p['balance']));
        }
    }

    public function save(RemittanceService $service): void
    {
        $this->authorizeWrite('recettes.modifier');
        $this->validate(['form.remitted_on' => ['required', 'date', 'before_or_equal:today'], 'form.period_from' => ['required', 'date'],
            'form.period_to' => ['required', 'date', 'after_or_equal:form.period_from'], 'form.amount' => ['required', 'numeric', 'min:0.01'],
            'form.payment_mode' => ['required', 'in:especes,cheque,virement'], 'form.accountant' => ['required', 'string', 'max:150']],
            [], ['form.amount' => 'montant', 'form.period_to' => 'fin de période']);
        try {
            $r = $service->create($this->form, auth()->user());
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw \Illuminate\Validation\ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn ($m, $k) => ['form.'.$k => $m])->all());
        }
        $this->showForm = false;
        $this->notify("Bordereau {$r->number} enregistré.");
    }

    public function askAck(int $id): void
    {
        $this->authorizeWrite('recettes.modifier');
        $this->ackId = $id;
        $this->ackOn = today()->toDateString();
        $this->ackRef = '';
    }

    public function confirmAck(RemittanceService $service): void
    {
        $this->authorizeWrite('recettes.modifier');
        $this->validate(['ackOn' => ['required', 'date'], 'ackRef' => ['nullable', 'string', 'max:60']]);
        $service->acknowledge(CashRemittance::findOrFail($this->ackId), $this->ackOn, $this->ackRef, auth()->user());
        $this->ackId = null;
        $this->notify('Accusé du comptable enregistré.');
    }

    public function askCancel(int $id): void
    {
        $this->authorizeWrite('paiements.valider');
        $this->cancelId = $id;
        $this->cancelReason = '';
        $this->resetValidation();
    }

    public function confirmCancel(RemittanceService $service): void
    {
        $this->authorizeWrite('paiements.valider');
        $this->validate(['cancelReason' => ['required', 'string', 'min:5', 'max:255']], [], ['cancelReason' => 'motif']);
        $r = CashRemittance::findOrFail($this->cancelId);
        if (! $r->isCancelled()) {
            try {
                $service->cancel($r, auth()->user(), $this->cancelReason);
            } catch (\Illuminate\Validation\ValidationException $e) {
                throw \Illuminate\Validation\ValidationException::withMessages(['cancelReason' => collect($e->errors())->flatten()->first()]);
            }
        }
        $this->cancelId = null;
        $this->notify('Bordereau annulé ; il reste visible avec son motif.', 'warning');
    }

    public function render(Ledger $ledger, RemittanceService $service)
    {
        return view('livewire.regie.versements', [
            'rows' => CashRemittance::with('author')->orderByDesc('remitted_on')->orderByDesc('id')->paginate(20),
            'balance' => $ledger->balanceAt(today()->toDateString()),
            'pending' => CashRemittance::active()->where('status', 'verse')->count(),
            'proposal' => $this->showForm && ! empty($this->form['period_from']) && ! empty($this->form['period_to']) ? $service->proposal($this->form['period_from'], $this->form['period_to']) : null,
        ]);
    }
}
