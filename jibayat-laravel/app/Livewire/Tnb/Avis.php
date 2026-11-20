<?php

namespace App\Livewire\Tnb;

use App\Domain\Tnb\Models\Parcel;
use App\Domain\Tnb\Services\TnbCalculator;
use App\Livewire\AppPage;
use Livewire\Attributes\Title;

/**
 * Avis de non-paiement TNB — Flask /tnb/<id>/avis_non_paiement et /tnb/avis_multiple :
 * terrains ayant des années dues, avis individuel ou lot d'avis (sélection).
 */
#[Title('Avis de non-paiement — TNB')]
class Avis extends AppPage
{
    public const ROUTE = 'tnb.avis';

    public int $minYears = 1;

    public string $zone = '';

    /** @var list<int> */
    public array $selected = [];

    public function selectAll(array $ids): void
    {
        $this->selected = count($this->selected) === count($ids) ? [] : array_map('intval', $ids);
    }

    public function render(TnbCalculator $calc)
    {
        $rows = Parcel::with('taxpayer')->where('is_active', true)->where('is_archived', false)->where('is_census', false)
            ->when($this->zone, fn ($q) => $q->where('zone', $this->zone))->orderBy('id')->get()
            ->map(fn ($p) => ['parcel' => $p] + $calc->situation($p))
            ->map(fn ($r) => $r + ['years' => array_merge(array_column($r['not_declared'], 'annee'), array_column($r['pending'], 'annee'))])
            ->filter(fn ($r) => count($r['years']) >= max(1, $this->minYears))
            ->sortByDesc('total_due')->values();

        return view('livewire.tnb.avis', [
            'rows' => $rows,
            'total' => $rows->sum('total_due'),
            'zones' => Parcel::where('is_active', true)->whereNotNull('zone')->distinct()->orderBy('zone')->pluck('zone'),
        ]);
    }
}
