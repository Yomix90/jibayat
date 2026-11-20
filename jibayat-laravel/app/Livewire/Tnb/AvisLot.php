<?php

namespace App\Livewire\Tnb;

use App\Domain\Tnb\Services\TnbCalculator;
use Livewire\Attributes\Title;

/** Avis en lot : même liste que les avis, tous les terrains débiteurs présélectionnés (Flask /tnb/avis_lot). */
#[Title('Avis en lot — TNB')]
class AvisLot extends Avis
{
    public const ROUTE = 'tnb.avis-lot';

    public function mount(TnbCalculator $calc): void
    {
        $this->selected = \App\Domain\Tnb\Models\Parcel::where('is_active', true)->where('is_archived', false)->where('is_census', false)->get()
            ->filter(fn ($p) => $calc->dueYears($p) !== [])->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
    }
}
