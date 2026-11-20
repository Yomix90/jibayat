<?php

namespace App\Livewire\DomainePublic;

use App\Domain\DomainePublic\Actions\DeclareOccupationPeriods;
use App\Domain\DomainePublic\Models\PublicOccupation;
use App\Domain\DomainePublic\Services\OccupationCalculator;
use App\Livewire\AppPage;
use App\Support\PyMath;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

/**
 * Encaisser la redevance d'occupation — Flask /occupation-domaine/<id>/paiement et /declarer.
 * Trimestres échus ou année entière (tarif × superficie, sans pénalité).
 */
#[Title('Encaisser — Domaine public')]
class Encaisser extends AppPage
{
    public const ROUTE = 'domaine-public.encaisser';

    #[Url(as: 'occupation')]
    public ?int $occupationId = null;

    #[Url(as: 'q')]
    public string $search = '';

    /** @var list<string> « AAAA_T » (T = 0 pour l'année entière) */
    public array $selected = [];

    public string $declared_on = '';

    public string $slip_number = '';

    public function mount(): void
    {
        $this->declared_on = today()->toDateString();
    }

    protected function occupation(): ?PublicOccupation
    {
        return $this->occupationId ? PublicOccupation::with('taxpayer')->where('is_active', true)->find($this->occupationId) : null;
    }

    public function choose(int $id): void
    {
        $this->occupationId = $id;
        $this->reset('selected', 'slip_number');
        $this->resetValidation();
    }

    /** Cocher l'année entière retire ses trimestres de la sélection. */
    public function updatedSelected(): void
    {
        $years = array_map(fn ($p) => explode('_', $p)[0], array_filter($this->selected, fn ($p) => str_ends_with($p, '_0')));
        $this->selected = array_values(array_filter($this->selected, fn ($p) => str_ends_with($p, '_0') || ! in_array(explode('_', $p)[0], $years, true)));
    }

    public function declare(DeclareOccupationPeriods $action): void
    {
        $this->authorizeWrite('bulletins.creer');
        $this->validate([
            'declared_on' => ['required', 'date', 'before_or_equal:today'],
            'slip_number' => ['nullable', 'string', 'max:40'],
            'selected' => ['required', 'array', 'min:1'],
        ], ['selected.required' => 'Cochez au moins une période.'], ['declared_on' => 'date', 'slip_number' => 'n° de bulletin']);
        try {
            $r = $action->handle($this->occupation(), $this->selected, auth()->user(), ['declared_on' => $this->declared_on, 'slip_number' => $this->slip_number]);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }
        $msg = count($r['declarations']).' période(s) liquidée(s) — '.number_format($r['total'], 2, ',', ' ').' DH';
        $msg .= $r['slip_number'] ? " · bulletin n° {$r['slip_number']} en attente du régisseur" : ' · sous le seuil de 100 DH, sans bulletin';
        $this->notify($msg);
        $this->reset('selected', 'slip_number');
    }

    public function render(OccupationCalculator $calc)
    {
        $o = $this->occupation();
        if (! $o) {
            $s = $this->search;
            $rows = PublicOccupation::with('taxpayer')->where('is_active', true)
                ->when($s, fn ($q) => $q->where(fn ($w) => $w->where('number', 'like', "%{$s}%")->orWhere('location', 'like', "%{$s}%")
                    ->orWhereHas('taxpayer', fn ($t) => $t->where('last_name', 'like', "%{$s}%")->orWhere('cin', 'like', "%{$s}%"))))
                ->orderBy('number')->limit(150)->get()
                ->map(fn ($x) => ['occupation' => $x] + $calc->situation($x))
                ->filter(fn ($r) => $r['nb_non_declares'] > 0);

            return view('livewire.domaine-public.encaisser-choose', ['rows' => $rows]);
        }

        $payable = $calc->payable($o);
        $years = [];
        foreach ($payable['quarters'] as $q) {
            $years[$q['annee']]['quarters'][] = $q + ['key' => "{$q['annee']}_{$q['trimestre']}", 'calc' => $calc->computePeriod($o, $q['annee'], $q['trimestre'])];
        }
        foreach ($payable['years'] as $y) {
            $years[$y]['year'] = ['key' => "{$y}_0", 'calc' => $calc->computePeriod($o, $y, 0)];
        }
        ksort($years);
        $amounts = [];
        foreach ($years as $y => $block) {
            foreach ($block['quarters'] ?? [] as $q) {
                $amounts[$q['key']] = $q['calc']['total'];
            }
            if (isset($block['year'])) {
                $amounts[$block['year']['key']] = $block['year']['calc']['total'];
            }
        }
        $history = DB::table('tax_declarations as d')
            ->leftJoin('payment_slips as s', fn ($j) => $j->on('s.tax_declaration_id', '=', 'd.id')->whereNull('s.cancelled_at'))
            ->where('d.module', OccupationCalculator::MODULE)->where('d.taxable_type', 'public_occupation')->where('d.taxable_id', $o->id)
            ->orderByDesc('d.year')->orderByDesc('d.quarter')->orderByDesc('d.id')
            ->get(['d.id', 'd.number', 'd.year', 'd.quarter', 'd.base_amount', 'd.rate', 'd.total', 'd.status', 'd.declared_on', 'd.cancelled_at', 'd.cancellation_reason',
                's.slip_number', 's.status as slip_status', 's.receipt_number', 's.rejection_reason']);

        return view('livewire.domaine-public.encaisser', [
            'occupation' => $o, 'situation' => $calc->situation($o), 'years' => $years,
            'total' => PyMath::round(array_sum(array_intersect_key($amounts, array_flip($this->selected))), 2),
            'history' => $history,
        ]);
    }
}
