<?php

namespace App\Livewire\Tnb;

use App\Domain\Tnb\Actions\DeclareTnbYears;
use App\Domain\Tnb\Models\Parcel;
use App\Domain\Tnb\Models\TnbFile;
use App\Domain\Tnb\Services\TnbCalculator;
use App\Livewire\AppPage;
use App\Support\PyMath;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

/**
 * Encaisser la TNB (أداء الرسم) — Flask /tnb/<id>/paiement et /multi_declarations.
 * Les années non déclarées sont calculées à la date de déclaration (pénalités, majoration, amende) ;
 * on coche les années, on saisit le n° de BV : une déclaration par année, un bulletin « en attente » chacun.
 */
#[Title('Encaisser la TNB')]
class Encaisser extends AppPage
{
    public const ROUTE = 'tnb.encaisser';

    #[Url(as: 'terrain')]
    public ?int $parcelId = null;

    #[Url(as: 'q')]
    public string $search = '';

    /** @var list<int> */
    public array $selected = [];

    public string $declared_on = '';

    public string $slip_number = '';

    public function mount(): void
    {
        $this->declared_on = today()->toDateString();
        $this->selectAll();
    }

    protected function parcel(): ?Parcel
    {
        return $this->parcelId ? Parcel::with('taxpayer')->where('is_active', true)->find($this->parcelId) : null;
    }

    protected function onDate(): CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($this->declared_on ?: today());
        } catch (\Throwable) {
            return CarbonImmutable::today();
        }
    }

    protected function selectAll(): void
    {
        $p = $this->parcel();
        $this->selected = $p ? array_column(app(TnbCalculator::class)->situation($p, $this->onDate())['not_declared'], 'annee') : [];
    }

    /**
     * Années émises : avec bulletin en attente (montant enregistré) ou à réémettre (bulletin rejeté ou déclaration
     * « sous seuil » sans bulletin — DeclareTnbYears les recalcule à la date choisie).
     */
    protected function openDeclarations(Parcel $parcel): array
    {
        $pending = $reissue = [];
        $rows = DB::table('tax_declarations as d')->where('d.module', 'TNB')->where('d.taxable_type', 'parcel')->where('d.taxable_id', $parcel->id)
            ->whereNotIn('d.status', ['paye', 'annule'])->whereNull('d.cancelled_at')
            ->selectRaw("d.year, d.total, d.status, (SELECT COUNT(*) FROM payment_slips s WHERE s.tax_declaration_id = d.id AND s.status = 'en_attente' AND s.cancelled_at IS NULL) AS waiting,
                (SELECT COUNT(*) FROM payment_slips s WHERE s.tax_declaration_id = d.id AND s.status = 'rejete' AND s.cancelled_at IS NULL) AS rejected")
            ->orderBy('d.year')->get();
        foreach ($rows as $r) {
            if ($r->waiting > 0) {
                $pending[(int) $r->year] = $r;
            } else {
                $reissue[(int) $r->year] = $r;
            }
        }

        return [$pending, $reissue];
    }

    public function choose(int $id): void
    {
        $this->parcelId = $id;
        $this->reset('slip_number');
        $this->resetValidation();
        $this->selectAll();
    }

    public function declare(DeclareTnbYears $action): void
    {
        $this->authorizeWrite('bulletins.creer');
        $this->validate([
            'declared_on' => ['required', 'date', 'before_or_equal:today'],
            'slip_number' => ['nullable', 'string', 'max:40'],
            'selected' => ['required', 'array', 'min:1'],
        ], ['selected.required' => 'Cochez au moins une année.'], ['declared_on' => 'date de déclaration', 'slip_number' => 'n° de bulletin']);

        try {
            $r = $action->handle($this->parcel(), $this->selected, auth()->user(), ['declared_on' => $this->declared_on, 'slip_number' => $this->slip_number]);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        $n = count($r['declarations']);
        $msg = $n ? "{$n} année(s) déclarée(s) — ".number_format($r['total'], 2, ',', ' ').' DH' : 'Aucune année à déclarer.';
        if ($r['slip_number']) {
            $msg .= " · bulletin n° {$r['slip_number']} en attente du régisseur";
        }
        if ($r['skipped']) {
            $msg .= ' · ignorées : '.implode(', ', $r['skipped']);
        }
        $this->notify($msg, $n ? 'success' : 'warning');
        $this->reset('slip_number');
        $this->selectAll();
    }

    public function render(TnbCalculator $calc)
    {
        $parcel = $this->parcel();
        if (! $parcel) {
            $s = $this->search;
            $rows = Parcel::with('taxpayer')->where('is_active', true)->where('is_archived', false)->where('is_census', false)
                ->when($s, fn ($q) => $q->where(fn ($w) => $w->where('number', 'like', "%{$s}%")->orWhere('land_title', 'like', "%{$s}%")
                    ->orWhere('parcel_number', 'like', "%{$s}%")->orWhere('subdivision', 'like', "%{$s}%")
                    ->orWhereHas('taxpayer', fn ($t) => $t->where('last_name', 'like', "%{$s}%")->orWhere('first_name', 'like', "%{$s}%")->orWhere('cin', 'like', "%{$s}%"))))
                ->orderBy('id')->limit(150)->get()
                ->map(fn ($p) => ['parcel' => $p] + $calc->situation($p))
                ->filter(fn ($r) => count($r['not_declared']) > 0);

            return view('livewire.tnb.encaisser-choose', ['rows' => $rows]);
        }

        $situation = $calc->situation($parcel, $this->onDate());
        [$pending, $reissue] = $this->openDeclarations($parcel);
        $selectable = $situation['not_declared'];
        foreach ($situation['pending'] as $y) {
            if (isset($reissue[$y['annee']])) {
                $selectable[] = $y + ['reissue' => $reissue[$y['annee']]];
            }
        }
        usort($selectable, fn ($a, $b) => $a['annee'] <=> $b['annee']);
        $chosen = array_values(array_filter($selectable, fn ($y) => in_array($y['annee'], array_map('intval', $this->selected), true)));
        $history = DB::table('tax_declarations as d')
            ->leftJoin('payment_slips as s', fn ($j) => $j->on('s.tax_declaration_id', '=', 'd.id')->whereNull('s.cancelled_at'))
            ->where('d.module', 'TNB')->where('d.taxable_type', 'parcel')->where('d.taxable_id', $parcel->id)
            ->orderByDesc('d.year')->orderByDesc('d.id')
            ->get(['d.id', 'd.number', 'd.year', 'd.total', 'd.status', 'd.declared_on', 'd.cancelled_at', 'd.cancellation_reason',
                's.slip_number', 's.status as slip_status', 's.receipt_number', 's.rejection_reason']);

        return view('livewire.tnb.encaisser', [
            'parcel' => $parcel,
            'file' => TnbFile::where('taxpayer_id', $parcel->taxpayer_id)->value('number'),
            'situation' => $situation,
            'selectable' => $selectable,
            'pendingRows' => $pending,
            'pendingTotal' => PyMath::round(array_sum(array_map(fn ($r) => (float) $r->total, $pending)), 2),
            'total' => PyMath::ceilCents(array_sum(array_column($chosen, 'total'))),
            'exempt' => $calc->exemptYears($parcel),
            'history' => $history,
            'paidTotal' => (float) $history->where('status', 'paye')->whereNull('cancelled_at')->sum('total'),
        ]);
    }
}
