<?php

namespace App\Livewire\Souk;

use App\Domain\Souk\Actions\CollectSoukMonths;
use App\Domain\Souk\Models\SoukContract;
use App\Domain\Souk\Services\SoukCalculator;
use App\Livewire\AppPage;
use App\Support\PyMath;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

/**
 * Encaisser les redevances d'affermage — Flask /souks/<id>/paiement et /payer.
 * On indique le nombre de mois à payer : ce sont toujours les plus anciens (ordre chronologique obligatoire).
 */
#[Title('Encaisser — Souk')]
class Encaisser extends AppPage
{
    public const ROUTE = 'souk.encaisser';

    #[Url(as: 'contrat')]
    public ?int $contractId = null;

    #[Url(as: 'q')]
    public string $search = '';

    public int $count = 0;

    public string $slip_number = '';

    public string $payment_mode = 'especes';

    public string $transfer_number = '';

    public string $notes = '';

    public function mount(): void
    {
        $this->count = count($this->available());
    }

    protected function contract(): ?SoukContract
    {
        return $this->contractId ? SoukContract::with('taxpayer')->where('is_active', true)->find($this->contractId) : null;
    }

    protected function available(): array
    {
        $c = $this->contract();

        return $c ? array_values(array_filter(app(SoukCalculator::class)->unpaidMonths($c), fn ($m) => ! $m['est_emis'])) : [];
    }

    public function choose(int $id): void
    {
        $this->contractId = $id;
        $this->reset('slip_number', 'transfer_number', 'notes');
        $this->resetValidation();
        $this->count = count($this->available());
    }

    public function collect(CollectSoukMonths $action): void
    {
        $this->authorizeWrite('bulletins.creer');
        $available = $this->available();
        $this->validate([
            'count' => ['required', 'integer', 'min:1', 'max:'.max(1, count($available))],
            'slip_number' => ['nullable', 'string', 'max:40'],
            'payment_mode' => ['required', 'in:especes,cheque,virement,bulletin_manuel'],
            'transfer_number' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [], ['count' => 'nombre de mois', 'slip_number' => 'n° de bulletin']);

        try {
            $d = $action->handle($this->contract(), array_column(array_slice($available, 0, $this->count), 'mois'), auth()->user(), [
                'slip_number' => $this->slip_number, 'payment_mode' => $this->payment_mode,
                'transfer_number' => $this->transfer_number, 'notes' => $this->notes,
            ]);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }
        $slip = $d->paymentSlips()->first();
        $this->notify("Bulletin n° {$slip->slip_number} créé — {$d->periods()->count()} mois — ".number_format((float) $d->total, 2, ',', ' ').' DH, en attente du régisseur.');
        $this->reset('slip_number', 'transfer_number', 'notes');
        $this->count = count($this->available());
    }

    public function render(SoukCalculator $calc)
    {
        $contract = $this->contract();
        if (! $contract) {
            $s = $this->search;
            $rows = SoukContract::with('taxpayer')->where('is_active', true)
                ->when($s, fn ($q) => $q->where(fn ($w) => $w->where('number', 'like', "%{$s}%")->orWhere('souk_name', 'like', "%{$s}%")
                    ->orWhereHas('taxpayer', fn ($t) => $t->where('last_name', 'like', "%{$s}%")->orWhere('cin', 'like', "%{$s}%"))))
                ->orderBy('souk_name')->limit(150)->get()
                ->map(fn ($c) => ['contract' => $c] + $calc->situation($c))
                ->filter(fn ($r) => $r['nb_non_declares'] > 0);

            return view('livewire.souk.encaisser-choose', ['rows' => $rows]);
        }

        $situation = $calc->situation($contract);
        $available = array_values(array_filter($situation['months'], fn ($m) => ! $m['est_emis']));
        $chosen = array_slice($available, 0, max(0, $this->count));

        return view('livewire.souk.encaisser', [
            'contract' => $contract, 'situation' => $situation, 'available' => $available,
            'chosen' => $chosen, 'groups' => SoukCalculator::groups($chosen),
            'total' => PyMath::round(array_sum(array_column($chosen, 'redevance')), 2),
            'history' => self::history($contract),
        ]);
    }

    public static function history(SoukContract $c)
    {
        return DB::table('tax_declarations as d')
            ->leftJoin('payment_slips as s', fn ($j) => $j->on('s.tax_declaration_id', '=', 'd.id')->whereNull('s.cancelled_at'))
            ->where('d.module', SoukCalculator::MODULE)->where('d.taxable_type', 'souk_contract')->where('d.taxable_id', $c->id)
            ->orderByDesc('d.id')->limit(50)
            ->get(['d.id', 'd.number', 'd.total', 'd.status', 'd.notes', 'd.declared_on', 'd.cancelled_at', 'd.cancellation_reason',
                's.slip_number', 's.status as slip_status', 's.receipt_number', 's.rejection_reason', 's.transfer_number']);
    }
}
