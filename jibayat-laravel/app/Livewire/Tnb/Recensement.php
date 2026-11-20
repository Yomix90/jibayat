<?php

namespace App\Livewire\Tnb;

use App\Domain\Tnb\Models\Parcel;
use App\Domain\Tnb\Services\ParcelService;
use App\Livewire\AppPage;
use App\Livewire\Tnb\Concerns\EditsParcels;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

/**
 * Recensement (استرجاع) — Flask /tnb/recensement : terrains repérés sans dossier officiel.
 * « Transférer » les fait passer dans la liste principale (le dossier du redevable est créé).
 */
#[Title('Recensement TNB')]
class Recensement extends AppPage
{
    use EditsParcels;

    public const ROUTE = 'tnb.recensement';

    #[Url(as: 'q')]
    public string $search = '';

    protected function isCensusScreen(): bool
    {
        return true;
    }

    public function promote(int $id, ParcelService $service): void
    {
        $this->authorizeWrite('tnb.ajouter');
        $parcel = Parcel::where('is_census', true)->where('is_active', true)->findOrFail($id);
        $file = $service->promoteCensus($parcel);
        activity('parametres')->causedBy(auth()->user())->performedOn($parcel)->log("Terrain {$parcel->number} transféré du recensement vers la liste TNB");
        $this->notify("Terrain n° {$parcel->number} transféré → dossier n° {$file->number}.");
    }

    public function remove(int $id): void
    {
        $this->authorizeWrite('tnb.supprimer');
        $parcel = Parcel::where('is_census', true)->findOrFail($id);
        $parcel->update(['is_active' => false]);
        $this->notify("Terrain n° {$parcel->number} retiré du recensement.");
    }

    public function render()
    {
        $s = mb_strtolower($this->search);
        $rows = Parcel::with('taxpayer')->where('is_census', true)->where('is_active', true)->latest()->get()
            ->filter(fn ($p) => $s === '' || str_contains(mb_strtolower(implode(' ', [$p->number, $p->taxpayer?->last_name, $p->taxpayer?->first_name,
                $p->taxpayer?->last_name_ar, $p->taxpayer?->first_name_ar, $p->taxpayer?->company_name, $p->taxpayer?->cin, $p->taxpayer?->rc,
                $p->address, $p->land_title, $p->parcel_number, $p->subdivision])), $s));

        return view('livewire.tnb.recensement', [
            'rows' => $rows,
            'taxpayers' => $this->showForm ? $this->taxpayerOptions() : collect(),
            'statuses' => self::parcelStatuses(),
        ]);
    }
}
