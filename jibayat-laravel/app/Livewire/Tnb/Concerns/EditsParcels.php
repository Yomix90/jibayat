<?php

namespace App\Livewire\Tnb\Concerns;

use App\Domain\Tnb\Models\Parcel;
use App\Domain\Tnb\Services\ParcelService;
use App\Models\Municipality;
use App\Models\Taxpayer;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Formulaire terrain partagé (liste TNB, recensement, fiche) — Flask tnb_ajouter / tnb_recensement_ajouter / tnb_modifier.
 */
trait EditsParcels
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = [];

    public bool $newTaxpayer = false;

    public array $taxpayerForm = ['last_name' => '', 'first_name' => '', 'last_name_ar' => '', 'first_name_ar' => '', 'cin' => '', 'phone' => ''];

    public string $taxpayerSearch = '';

    protected function emptyParcelForm(): array
    {
        return ['number' => '', 'taxpayer_id' => '', 'address' => '', 'address_ar' => '', 'district' => '', 'subdivision' => '',
            'borough' => '', 'area' => '', 'zone' => 'B', 'land_title' => '', 'parcel_number' => '', 'status' => 'non_bati',
            'acquired_on' => '', 'tax_start_year' => ''];
    }

    public function createParcel(): void
    {
        $this->authorizeWrite('tnb.ajouter');
        $this->reset('editingId', 'newTaxpayer', 'taxpayerSearch');
        $this->taxpayerForm = array_map(fn () => '', $this->taxpayerForm);
        $this->form = ['number' => ParcelService::nextNumber()] + $this->emptyParcelForm();
        $this->resetValidation();
        $this->showForm = true;
    }

    public function editParcel(int $id): void
    {
        $this->authorizeWrite('tnb.modifier');
        $p = Parcel::findOrFail($id);
        $this->editingId = $p->id;
        $this->newTaxpayer = false;
        $this->form = collect($this->emptyParcelForm())->mapWithKeys(fn ($v, $k) => [$k => match ($k) {
            'acquired_on' => $p->acquired_on?->toDateString() ?? '',
            'area' => $p->area !== null ? (string) (float) $p->area : '',
            default => (string) ($p->{$k} ?? ''),
        }])->all();
        $this->resetValidation();
        $this->showForm = true;
    }

    public function saveParcel(ParcelService $service): void
    {
        $this->authorizeWrite($this->editingId ? 'tnb.modifier' : 'tnb.ajouter');

        if ($this->newTaxpayer && ! $this->editingId) {
            $t = $this->validate([
                'taxpayerForm.last_name' => ['required', 'string', 'max:255'],
                'taxpayerForm.first_name' => ['nullable', 'string', 'max:255'],
                'taxpayerForm.last_name_ar' => ['nullable', 'string', 'max:255'],
                'taxpayerForm.first_name_ar' => ['nullable', 'string', 'max:255'],
                'taxpayerForm.cin' => ['nullable', 'string', 'max:30'],
                'taxpayerForm.phone' => ['nullable', 'string', 'max:40'],
            ], [], ['taxpayerForm.last_name' => 'nom du redevable'])['taxpayerForm'];
            $taxpayer = Taxpayer::create(array_map(fn ($v) => $v === '' ? null : $v, $t) + [
                'number' => 'CTB-'.(Taxpayer::max('id') + 1), 'person_type' => 'physique', 'is_active' => true,
                'municipality_id' => Municipality::value('id'),
            ]);
            $this->form['taxpayer_id'] = (string) $taxpayer->id;
            $this->newTaxpayer = false;
        }

        $data = $this->validate([
            'form.number' => ['required', 'string', 'max:40', Rule::unique('parcels', 'number')->ignore($this->editingId)],
            'form.taxpayer_id' => [$this->editingId ? 'nullable' : 'required', 'exists:taxpayers,id'],
            'form.address' => ['nullable', 'string', 'max:255'],
            'form.address_ar' => ['nullable', 'string', 'max:255'],
            'form.district' => ['nullable', 'string', 'max:255'],
            'form.subdivision' => ['nullable', 'string', 'max:255'],
            'form.borough' => ['nullable', 'string', 'max:255'],
            'form.area' => ['required', 'numeric', 'min:0'],
            'form.zone' => ['required', 'string', 'max:10'],
            'form.land_title' => ['nullable', 'string', 'max:60'],
            'form.parcel_number' => ['nullable', 'string', 'max:60'],
            'form.status' => ['required', Rule::in(array_keys(self::parcelStatuses()))],
            'form.acquired_on' => ['nullable', 'date'],
            'form.tax_start_year' => ['nullable', 'integer', 'min:1990', 'max:'.(now()->year + 1)],
        ], [], ['form.number' => 'n° de terrain', 'form.taxpayer_id' => 'redevable', 'form.area' => 'superficie'])['form'];
        $data = array_map(fn ($v) => $v === '' ? null : $v, $data);
        $data['zone'] = mb_strtoupper($data['zone']);

        if ($this->editingId) {
            unset($data['taxpayer_id']);   // le propriétaire change par « Transfert », pas par modification
            Parcel::findOrFail($this->editingId)->update($data);
            $this->notify('Terrain modifié.');
        } else {
            $parcel = $service->create($data + ['municipality_id' => Municipality::value('id')], $this->isCensusScreen());
            $this->notify($this->isCensusScreen()
                ? "Terrain n° {$parcel->number} ajouté au recensement."
                : "Terrain n° {$parcel->number} ajouté au dossier n° {$service->fileFor($parcel->taxpayer_id)->number}.");
        }
        $this->showForm = false;
    }

    protected function isCensusScreen(): bool
    {
        return false;
    }

    public static function parcelStatuses(): array
    {
        return ['non_bati' => 'Non bâti', 'en_construction' => 'En construction', 'construit' => 'Construit (bâti)'];
    }

    protected function taxpayerOptions(): Collection
    {
        $s = $this->taxpayerSearch;

        return Taxpayer::where('is_active', true)
            ->when($s, fn ($q) => $q->where(fn ($w) => $w->where('last_name', 'like', "%{$s}%")->orWhere('first_name', 'like', "%{$s}%")
                ->orWhere('last_name_ar', 'like', "%{$s}%")->orWhere('cin', 'like', "%{$s}%")->orWhere('company_name', 'like', "%{$s}%")))
            ->orWhere('id', $this->form['taxpayer_id'] ?? 0)
            ->orderBy('last_name')->limit(50)->get();
    }
}
