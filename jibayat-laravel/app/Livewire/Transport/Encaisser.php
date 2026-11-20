<?php

namespace App\Livewire\Transport;

use App\Domain\Transport\Actions\PayVehicleQuarters;
use App\Domain\Transport\Models\Vehicle;
use App\Domain\Transport\Services\TransportCalculator;
use App\Domain\Boissons\Services\BeverageCalculator;
use App\Livewire\AppPage;
use App\Support\PyMath;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

/**
 * Encaisser stationnement + TPV — Flask /stationnement/<id>/paiement et /payer.
 * On coche les trimestres échus ; montant = stationnement + TPV du trimestre, pénalité et majoration sur la TPV
 * si le paiement est après l'échéance.
 */
#[Title('Encaisser — Transport')]
class Encaisser extends AppPage
{
    public const ROUTE = 'transport.encaisser';

    #[Url(as: 'vehicule')]
    public ?int $vehicleId = null;

    #[Url(as: 'q')]
    public string $search = '';

    /** @var list<string> */
    public array $selected = [];

    public string $paid_on = '';

    public string $slip_number = '';

    public function mount(): void
    {
        $this->paid_on = today()->toDateString();
        $this->selectAll();
    }

    protected function vehicle(): ?Vehicle
    {
        return $this->vehicleId ? Vehicle::with('taxpayer')->where('is_active', true)->find($this->vehicleId) : null;
    }

    protected function selectAll(): void
    {
        $v = $this->vehicle();
        $this->selected = $v ? array_map(fn ($q) => "{$q['annee']}_{$q['trimestre']}", app(TransportCalculator::class)->unpaidQuarters($v)) : [];
    }

    public function choose(int $id): void
    {
        $this->vehicleId = $id;
        $this->reset('slip_number');
        $this->resetValidation();
        $this->selectAll();
    }

    public function pay(PayVehicleQuarters $action): void
    {
        $this->authorizeWrite('bulletins.creer');
        $this->validate([
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'slip_number' => ['nullable', 'string', 'max:40'],
            'selected' => ['required', 'array', 'min:1'],
        ], ['selected.required' => 'Cochez au moins un trimestre.'], ['paid_on' => 'date de paiement', 'slip_number' => 'n° de bulletin']);
        try {
            $r = $action->handle($this->vehicle(), $this->selected, auth()->user(), ['paid_on' => $this->paid_on, 'slip_number' => $this->slip_number]);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }
        $n = count($r['declarations']);
        $msg = "{$n} trimestre(s) enregistré(s) — ".number_format($r['total'], 2, ',', ' ').' DH';
        $msg .= $r['slip_number'] ? " · bulletin n° {$r['slip_number']} en attente du régisseur" : '';
        if ($r['skipped']) {
            $msg .= ' · déjà enregistrés : '.implode(', ', $r['skipped']);
        }
        $this->notify($msg, $n ? 'success' : 'warning');
        $this->reset('slip_number');
        $this->selectAll();
    }

    public function render(TransportCalculator $calc)
    {
        $vehicle = $this->vehicle();
        if (! $vehicle) {
            $s = $this->search;
            $rows = Vehicle::with('taxpayer')->where('is_active', true)
                ->when($s, fn ($q) => $q->where(fn ($w) => $w->where('registration', 'like', "%{$s}%")->orWhere('number', 'like', "%{$s}%")
                    ->orWhereHas('taxpayer', fn ($t) => $t->where('last_name', 'like', "%{$s}%")->orWhere('cin', 'like', "%{$s}%"))))
                ->orderBy('registration')->limit(150)->get()
                ->map(fn ($v) => ['vehicle' => $v, 'quarters' => $calc->unpaidQuarters($v)])
                ->filter(fn ($r) => count($r['quarters']) > 0);

            return view('livewire.transport.encaisser-choose', ['rows' => $rows]);
        }

        $date = $this->paid_on ?: today()->toDateString();
        $lines = [];
        foreach ($calc->unpaidQuarters($vehicle) as $q) {
            [$sta, $tpv] = $calc->amounts($vehicle, $q['annee'], $q['trimestre']);
            $key = "{$q['annee']}_{$q['trimestre']}";
            $lines[$key] = $q + ['key' => $key, 'calc' => $calc->computeQuarter($sta, $tpv, $q['annee'], $q['trimestre'], $date)];
        }
        $chosen = array_filter($lines, fn ($l) => in_array($l['key'], $this->selected, true));
        $history = DB::table('tax_declarations as d')
            ->leftJoin('payment_slips as s', fn ($j) => $j->on('s.tax_declaration_id', '=', 'd.id')->whereNull('s.cancelled_at'))
            ->where('d.module', TransportCalculator::MODULE)->where('d.taxable_type', 'vehicle')->where('d.taxable_id', $vehicle->id)
            ->orderByDesc('d.year')->orderByDesc('d.quarter')->orderByDesc('d.id')
            ->get(['d.id', 'd.number', 'd.year', 'd.quarter', 'd.base_amount', 'd.rate', 'd.total', 'd.status', 'd.declared_on', 'd.cancelled_at', 'd.cancellation_reason',
                's.slip_number', 's.status as slip_status', 's.receipt_number', 's.rejection_reason']);
        $open = $history->whereNull('cancelled_at')->whereNotIn('status', ['paye', 'annule']);

        return view('livewire.transport.encaisser', [
            'vehicle' => $vehicle, 'lines' => $lines, 'byYear' => collect($lines)->groupBy('annee'),
            'total' => PyMath::round(array_sum(array_map(fn ($l) => $l['calc']['total'], $chosen)), 2),
            'amounts' => $calc->amounts($vehicle),
            'history' => $history,
            'pendingTotal' => (float) $open->sum('total'),
            'paidTotal' => (float) $history->whereNull('cancelled_at')->where('status', 'paye')->sum('total'),
            'period' => BeverageCalculator::periodLabel(array_values($lines)),
        ]);
    }
}
