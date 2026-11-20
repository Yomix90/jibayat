<?php

namespace App\Livewire\Locations;

use App\Domain\Locations\Actions\CollectRent;
use App\Domain\Locations\Models\Lease;
use App\Domain\Locations\Services\LeaseCalculator;
use App\Livewire\AppPage;
use App\Support\PyMath;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

/**
 * Encaisser des loyers — Flask : /location-locaux/<id>/paiement et /payer.
 * On coche les mois (ou on indique un nombre de mois, pris dans l'ordre chronologique) ;
 * le total est la somme des tarifs de chaque mois. Le bulletin part « en attente » chez le régisseur.
 */
#[Title('Encaisser des loyers')]
class Encaisser extends AppPage
{
    public const ROUTE = 'locations.encaisser';

    #[Url(as: 'bail')]
    public ?int $leaseId = null;

    #[Url(as: 'q')]
    public string $search = '';

    /** @var list<string> */
    public array $selected = [];

    public ?int $count = null;

    public string $slip_number = '';

    public string $payment_mode = 'especes';

    public string $paid_on = '';

    public string $notes = '';

    public function mount(): void
    {
        $this->paid_on = today()->toDateString();
        $this->selectFirst();
    }

    protected function lease(): ?Lease
    {
        return $this->leaseId ? Lease::with(['taxpayer', 'shop', 'leaseTariff', 'leaseSector'])->find($this->leaseId) : null;
    }

    protected function available(): array
    {
        $lease = $this->lease();

        return $lease ? array_values(array_filter(app(LeaseCalculator::class)->unpaidMonths($lease), fn ($m) => ! $m['est_emis'])) : [];
    }

    /** Par défaut tous les mois disponibles sont cochés (Flask : nb_mois = tous). */
    protected function selectFirst(?int $n = null): void
    {
        $months = array_column($this->available(), 'mois');
        $this->selected = $n === null ? $months : array_slice($months, 0, max(0, $n));
        $this->count = count($this->selected);
    }

    public function updatedCount($value): void
    {
        $this->selectFirst(max(0, (int) $value));
    }

    public function updatedSelected(): void
    {
        $this->count = count($this->selected);
    }

    public function choose(int $id): void
    {
        $this->leaseId = $id;
        $this->reset('slip_number', 'notes');
        $this->resetValidation();
        $this->selectFirst();
    }

    public function collect(CollectRent $action)
    {
        $this->authorizeWrite('bulletins.creer');
        $this->validate([
            'slip_number' => ['nullable', 'string', 'max:40'],
            'payment_mode' => ['required', 'in:especes,cheque,virement,bulletin_manuel'],
            'paid_on' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [], ['slip_number' => 'n° de bulletin', 'paid_on' => 'date de paiement']);

        try {
            $declaration = $action->handle($this->lease(), $this->selected, auth()->user(), [
                'slip_number' => $this->slip_number, 'payment_mode' => $this->payment_mode,
                'paid_on' => $this->paid_on, 'notes' => $this->notes,
            ]);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return null;
        }

        $slip = $declaration->paymentSlips()->first();
        $this->notify("Bulletin n° {$slip->slip_number} créé — {$declaration->periods()->count()} mois — "
            .number_format((float) $declaration->total, 2, ',', ' ').' DH, en attente du régisseur.');
        $this->reset('slip_number', 'notes');
        $this->selectFirst();
    }

    public function render(LeaseCalculator $calc)
    {
        $lease = $this->lease();
        if (! $lease) {
            $leases = Lease::with(['taxpayer', 'shop'])->where('is_active', true)
                ->when($this->search, fn ($q) => $q->where(fn ($w) => $w->where('number', 'like', "%{$this->search}%")
                    ->orWhere('premises_ref', 'like', "%{$this->search}%")
                    ->orWhereHas('taxpayer', fn ($t) => $t->where('last_name', 'like', "%{$this->search}%")->orWhere('cin', 'like', "%{$this->search}%"))))
                ->orderBy('number')->limit(100)->get()
                ->map(fn ($l) => ['lease' => $l] + $calc->situation($l))
                ->filter(fn ($r) => $r['nb_non_declares'] > 0);

            return view('livewire.locations.encaisser-choose', ['rows' => $leases]);
        }

        $months = $calc->unpaidMonths($lease);
        $available = array_values(array_filter($months, fn ($m) => ! $m['est_emis']));
        $chosen = array_values(array_filter($available, fn ($m) => in_array($m['mois'], $this->selected, true)));

        $history = DB::table('tax_declarations as d')
            ->leftJoin('payment_slips as s', fn ($j) => $j->on('s.tax_declaration_id', '=', 'd.id')->whereNull('s.cancelled_at'))
            ->where('d.taxable_type', 'lease')->where('d.taxable_id', $lease->id)
            ->orderByDesc('d.id')->limit(30)
            ->get(['d.id', 'd.number', 'd.total', 'd.status', 'd.notes', 'd.declared_on', 'd.cancelled_at',
                's.slip_number', 's.status as slip_status', 's.receipt_number', 's.receipt_date', 's.rejection_reason']);

        return view('livewire.locations.encaisser', [
            'lease' => $lease,
            'rent' => $calc->monthlyRent($lease),
            'months' => $months,
            'available' => $available,
            'groups' => $calc->groupByTariff($chosen),
            'total' => PyMath::round(array_sum(array_column($chosen, 'tarif')), 2),
            'dueTotal' => LeaseCalculator::total($months),
            'pendingTotal' => LeaseCalculator::total(array_filter($months, fn ($m) => $m['est_emis'])),
            'paidTotal' => (float) $history->where('status', 'paye')->sum('total'),
            'period' => LeaseCalculator::periodLabel($months),
            'periodAr' => LeaseCalculator::periodLabel($months, true),
            'history' => $history,
        ]);
    }
}
