<?php

namespace App\Livewire\Boissons;

use App\Domain\Boissons\Actions\DeclareBeverageQuarters;
use App\Domain\Boissons\Models\BeverageOutlet;
use App\Domain\Boissons\Services\BeverageCalculator;
use App\Livewire\AppPage;
use App\Support\PyMath;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

/**
 * Encaisser la taxe sur les débits de boissons — Flask /debits-boissons/<id>/paiement et /declarer.
 * On coche les trimestres échus, on saisit le chiffre d'affaires de chacun ; le calcul (principal, pénalité,
 * majoration, amende) se fait à la date de déclaration ; un bulletin « en attente » par trimestre émis, même n° de BV.
 */
#[Title('Encaisser — Débits de boissons')]
class Encaisser extends AppPage
{
    public const ROUTE = 'boissons.encaisser';

    #[Url(as: 'etablissement')]
    public ?int $outletId = null;

    #[Url(as: 'q')]
    public string $search = '';

    /** @var list<string> clés « annee_trim » cochées */
    public array $selected = [];

    /** @var array<string, string> CA déclaré par trimestre */
    public array $bases = [];

    public string $declared_on = '';

    public string $slip_number = '';

    public function mount(): void
    {
        $this->declared_on = today()->toDateString();
    }

    protected function outlet(): ?BeverageOutlet
    {
        return $this->outletId ? BeverageOutlet::with(['taxpayer', 'tariff'])->where('is_active', true)->find($this->outletId) : null;
    }

    public function choose(int $id): void
    {
        $this->outletId = $id;
        $this->reset('selected', 'bases', 'slip_number');
        $this->resetValidation();
    }

    /** Saisir un CA coche le trimestre. */
    public function updatedBases($value, $key): void
    {
        if (is_numeric($value) && ! in_array($key, $this->selected, true)) {
            $this->selected[] = $key;
        }
    }

    public function declare(DeclareBeverageQuarters $action): void
    {
        $this->authorizeWrite('bulletins.creer');
        $rules = [
            'declared_on' => ['required', 'date', 'before_or_equal:today'],
            'slip_number' => ['nullable', 'string', 'max:40'],
            'selected' => ['required', 'array', 'min:1'],
        ];
        foreach ($this->selected as $key) {
            $rules["bases.{$key}"] = ['required', 'numeric', 'min:0'];
        }
        $this->validate($rules, ['selected.required' => 'Cochez au moins un trimestre.', 'bases.*.required' => 'Saisissez le chiffre d’affaires.'],
            ['declared_on' => 'date de déclaration', 'slip_number' => 'n° de bulletin']);

        $bases = collect($this->selected)->mapWithKeys(fn ($k) => [$k => (float) $this->bases[$k]])->all();
        try {
            $r = $action->handle($this->outlet(), $bases, auth()->user(), ['declared_on' => $this->declared_on, 'slip_number' => $this->slip_number]);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }
        $n = count($r['declarations']);
        $msg = "{$n} trimestre(s) déclaré(s) — ".number_format($r['total'], 2, ',', ' ').' DH';
        $msg .= $r['slip_number'] ? " · bulletin n° {$r['slip_number']} en attente du régisseur" : ' · aucun bulletin (sous seuil)';
        if ($r['skipped']) {
            $msg .= ' · déjà déclarés : '.implode(', ', $r['skipped']);
        }
        $this->notify($msg, $n ? 'success' : 'warning');
        $this->reset('selected', 'bases', 'slip_number');
    }

    public function render(BeverageCalculator $calc)
    {
        $outlet = $this->outlet();
        if (! $outlet) {
            $s = $this->search;
            $rows = BeverageOutlet::with('taxpayer')->where('is_active', true)
                ->when($s, fn ($q) => $q->where(fn ($w) => $w->where('number', 'like', "%{$s}%")->orWhere('name', 'like', "%{$s}%")
                    ->orWhereHas('taxpayer', fn ($t) => $t->where('last_name', 'like', "%{$s}%")->orWhere('company_name', 'like', "%{$s}%")->orWhere('cin', 'like', "%{$s}%"))))
                ->orderBy('name')->limit(150)->get()
                ->map(fn ($o) => ['outlet' => $o, 'quarters' => $calc->unpaidQuarters($o)])
                ->filter(fn ($r) => count($r['quarters']) > 0);

            return view('livewire.boissons.encaisser-choose', ['rows' => $rows]);
        }

        $rate = $calc->rateForOutlet($outlet);
        $quarters = $calc->unpaidQuarters($outlet);
        $date = $this->declared_on ?: today()->toDateString();
        $lines = [];
        foreach ($quarters as $q) {
            $key = "{$q['annee']}_{$q['trimestre']}";
            $base = $this->bases[$key] ?? '';
            $qRate = $calc->rateForQuarter($outlet, $q['annee'], $q['trimestre']);
            $lines[$key] = $q + ['key' => $key, 'rate' => $qRate, 'calc' => is_numeric($base) ? $calc->computeQuarter((float) $base, $qRate, $q['annee'], $q['trimestre'], $date, $outlet->id) : null];
        }
        $chosen = array_filter($lines, fn ($l) => in_array($l['key'], $this->selected, true) && $l['calc']);
        $history = DB::table('tax_declarations as d')
            ->leftJoin('payment_slips as s', fn ($j) => $j->on('s.tax_declaration_id', '=', 'd.id')->whereNull('s.cancelled_at'))
            ->where('d.module', BeverageCalculator::MODULE)->where('d.taxable_type', 'beverage_outlet')->where('d.taxable_id', $outlet->id)
            ->orderByDesc('d.year')->orderByDesc('d.quarter')->orderByDesc('d.id')
            ->get(['d.id', 'd.number', 'd.year', 'd.quarter', 'd.base_amount', 'd.total', 'd.status', 'd.declared_on', 'd.cancelled_at', 'd.cancellation_reason',
                's.slip_number', 's.status as slip_status', 's.receipt_number', 's.rejection_reason']);
        $open = $history->whereNull('cancelled_at')->whereNotIn('status', ['paye', 'annule']);

        return view('livewire.boissons.encaisser', [
            'outlet' => $outlet, 'rate' => $rate, 'lines' => $lines,
            'byYear' => collect($lines)->groupBy('annee'),
            'total' => PyMath::round(array_sum(array_map(fn ($l) => $l['calc']['total'], $chosen)), 2),
            'history' => $history,
            'pendingTotal' => (float) $open->sum('total'),
            'paidTotal' => (float) $history->whereNull('cancelled_at')->where('status', 'paye')->sum('total'),
            'period' => BeverageCalculator::periodLabel($quarters),
        ]);
    }
}
