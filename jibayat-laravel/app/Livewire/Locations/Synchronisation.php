<?php

namespace App\Livewire\Locations;

use App\Domain\Locations\Models\LeaseTariff;
use App\Domain\Locations\Services\LeaseTariffSync;
use App\Livewire\AppPage;
use App\Models\TaxOrder;
use Livewire\Attributes\Title;

/**
 * Synchronisation des tarifs de location avec l'arrêté fiscal — Flask : /location-locaux/tarifs/sync-fiscal
 * et /tarifs/<id>/sync-fiscal.
 */
#[Title('Synchronisation des tarifs')]
class Synchronisation extends AppPage
{
    public const ROUTE = 'locations.synchronisation';

    public function syncAll(LeaseTariffSync $sync): void
    {
        $this->authorizeWrite('config.gerer');
        $n = $sync->syncAll();
        $this->notify("{$n} tarif(s) synchronisé(s) avec l'arrêté fiscal.");
    }

    public function syncOne(int $id, LeaseTariffSync $sync): void
    {
        $this->authorizeWrite('config.gerer');
        $sync->sync(LeaseTariff::findOrFail($id))
            ? $this->notify('Tarif synchronisé.')
            : $this->notify('Synchronisation impossible : aucun arrêté fiscal ou rubrique « LOCATION_LOCAUX ».', 'error');
    }

    public function render(LeaseTariffSync $sync)
    {
        $tariffs = LeaseTariff::with('leaseSector', 'tariff.taxOrder')->orderBy('lease_sector_id')->orderBy('label')->get();

        return view('livewire.locations.synchronisation', [
            'tariffs' => $tariffs,
            'status' => $tariffs->mapWithKeys(fn ($t) => [$t->id => $sync->status($t)]),
            'activeOrder' => TaxOrder::where('status', 'actif')->orderByDesc('id')->first(),
        ]);
    }
}
