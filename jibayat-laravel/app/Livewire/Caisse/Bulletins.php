<?php

namespace App\Livewire\Caisse;

use App\Domain\Caisse\Models\PaymentSlip;
use App\Domain\Caisse\Services\CashierService;
use App\Domain\Dashboard\DashboardStats;
use App\Livewire\AppPage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * Caisse du régisseur — Flask : /paiements (bulletins en attente groupés par n° de BV, solder & quittancer,
 * validation groupée, rejet, correction, annulation). Sous-pages : rejetés, encaissements.
 */
#[Title('Bulletins en attente')]
class Bulletins extends AppPage
{
    use WithPagination;

    public const ROUTE = 'caisse.bulletins';

    /** en_attente | rejete | paye */
    public string $status = 'en_attente';

    /** Cases à cocher pour solder plusieurs bulletins sous une même quittance. */
    public bool $bulk = false;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'module')]
    public string $module = '';

    // Validation
    public bool $showValidate = false;

    /** @var array<int> */
    public array $selected = [];

    public string $receipt_number = '';

    public string $receipt_date = '';

    // Rejet / correction / annulation
    public bool $showAction = false;

    public string $action = '';

    public ?int $slipId = null;

    public string $reason = '';

    public string $slip_number = '';

    public string $payment_mode = 'especes';

    public bool $cancel_declaration = false;

    public function updating($name): void
    {
        if (in_array($name, ['search', 'module'], true)) {
            $this->resetPage();
        }
    }

    public function openValidate(?int $slipId = null): void
    {
        $this->authorizeWrite('paiements.valider');
        if ($slipId) {
            $this->selected = [$slipId];
        }
        if (! $this->selected) {
            $this->notify('Cochez au moins un bulletin.', 'error');

            return;
        }
        $this->receipt_number = '';
        $this->receipt_date = today()->toDateString();
        $this->resetValidation();
        $this->showValidate = true;
    }

    public function validateSlips(CashierService $cashier): void
    {
        $this->authorizeWrite('paiements.valider');
        $this->validate(['receipt_number' => ['required', 'string', 'max:40'], 'receipt_date' => ['required', 'date']], [],
            ['receipt_number' => 'n° de quittance', 'receipt_date' => 'date de quittance']);
        try {
            $n = count($this->selected) === 1
                ? $cashier->validate(PaymentSlip::findOrFail($this->selected[0]), $this->receipt_number, $this->receipt_date, auth()->user())
                : $cashier->validateMany($this->selected, $this->receipt_number, $this->receipt_date, auth()->user());
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }
        $this->notify("Paiement validé — quittance n° {$this->receipt_number} — {$n} déclaration(s) soldée(s).");
        $this->showValidate = false;
        $this->selected = [];
    }

    public function openAction(string $action, int $slipId): void
    {
        $this->authorizeWrite($action === 'correct' ? 'bulletins.creer' : ($action === 'reject' ? 'paiements.valider' : 'caisse.supprimer'));
        $slip = PaymentSlip::findOrFail($slipId);
        $this->action = $action;
        $this->slipId = $slipId;
        $this->reason = '';
        $this->slip_number = (string) $slip->slip_number;
        $this->payment_mode = $slip->payment_mode ?: 'especes';
        $this->cancel_declaration = false;
        $this->resetValidation();
        $this->showAction = true;
    }

    public function runAction(CashierService $cashier): void
    {
        $slip = PaymentSlip::findOrFail($this->slipId);
        try {
            match ($this->action) {
                'reject' => $this->doReject($cashier, $slip),
                'correct' => $this->doCorrect($cashier, $slip),
                'cancel' => $this->doCancel($cashier, $slip),
            };
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }
        $this->showAction = false;
    }

    protected function doReject(CashierService $cashier, PaymentSlip $slip): void
    {
        $this->authorizeWrite('paiements.valider');
        $this->validate(['reason' => ['required', 'string', 'max:255']], [], ['reason' => 'motif']);
        $cashier->reject($slip, $this->reason, auth()->user());
        $this->notify("Bulletin n° {$slip->slip_number} rejeté.");
    }

    protected function doCorrect(CashierService $cashier, PaymentSlip $slip): void
    {
        $this->authorizeWrite('bulletins.creer');
        $this->validate(['slip_number' => ['required', 'string', 'max:40'], 'payment_mode' => ['required', 'string']]);
        $cashier->correct($slip, $this->slip_number, $this->payment_mode, $this->reason ?: null, auth()->user());
        $this->notify("Bulletin n° {$this->slip_number} réémis, en attente du régisseur.");
    }

    protected function doCancel(CashierService $cashier, PaymentSlip $slip): void
    {
        $this->authorizeWrite('caisse.supprimer');
        $this->validate(['reason' => ['required', 'string', 'min:5', 'max:255']], [], ['reason' => 'motif']);
        $cashier->cancel($slip, $this->reason, auth()->user(), $this->cancel_declaration);
        $this->notify("Bulletin n° {$slip->slip_number} annulé.");
    }

    public function render()
    {
        $base = PaymentSlip::query()->from('payment_slips as s')
            ->join('tax_declarations as d', 'd.id', '=', 's.tax_declaration_id')
            ->leftJoin('taxpayers as t', 't.id', '=', 's.taxpayer_id')
            ->whereNull('s.cancelled_at')->where('s.status', $this->status)
            ->when($this->module, fn ($q) => $q->where('d.module', $this->module))
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w->where('s.slip_number', 'like', "%{$this->search}%")
                ->orWhere('s.receipt_number', 'like', "%{$this->search}%")->orWhere('d.number', 'like', "%{$this->search}%")
                ->orWhere('t.last_name', 'like', "%{$this->search}%")->orWhere('t.cin', 'like', "%{$this->search}%")));

        $slips = (clone $base)->select(['s.*', 'd.number as decl_number', 'd.module', 'd.year', 'd.quarter', 'd.notes as decl_notes',
            't.last_name', 't.first_name', 't.company_name', 't.cin'])
            ->orderByDesc($this->status === 'paye' ? 's.receipt_date' : 's.id')->paginate(40);

        $kpi = [
            'pending' => PaymentSlip::whereNull('cancelled_at')->where('status', 'en_attente'),
            'paid' => PaymentSlip::whereNull('cancelled_at')->where('status', 'paye'),
            'rejected' => PaymentSlip::whereNull('cancelled_at')->where('status', 'rejete'),
        ];

        return view('livewire.caisse.bulletins', [
            'slips' => $slips,
            'groups' => collect($slips->items())->groupBy(fn ($s) => $s->slip_number ?: 'sans n°'),
            'kpi' => collect($kpi)->map(fn ($q) => ['count' => (clone $q)->count(), 'amount' => (float) (clone $q)->sum('amount')]),
            'modules' => collect(DashboardStats::MODULES)->map(fn ($m) => $m['label']),
        ]);
    }
}
