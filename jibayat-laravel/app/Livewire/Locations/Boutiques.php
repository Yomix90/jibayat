<?php

namespace App\Livewire\Locations;

use App\Domain\Locations\Models\Lease;
use App\Domain\Locations\Models\Shop;
use App\Livewire\AppPage;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

/**
 * Boutiques — Flask : bloc « Boutiques disponibles / non exploitées » de /location-locaux/secteurs.
 */
#[Title('Boutiques')]
class Boutiques extends AppPage
{
    public const ROUTE = 'locations.boutiques';

    #[Url(as: 'statut')]
    public string $status = 'disponible';

    public function render()
    {
        $shops = Shop::with('leaseTariff.leaseSector')
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->orderBy('number')->get();
        $occupants = Lease::with('taxpayer')->where('is_active', true)->whereIn('shop_id', $shops->pluck('id'))->get()->keyBy('shop_id');

        return view('livewire.locations.boutiques', [
            'shops' => $shops,
            'occupants' => $occupants,
            'counts' => Shop::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
        ]);
    }
}
