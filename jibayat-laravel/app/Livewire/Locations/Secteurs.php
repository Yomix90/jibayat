<?php

namespace App\Livewire\Locations;

use App\Domain\Locations\Models\Lease;
use App\Domain\Locations\Models\LeaseSector;
use App\Domain\Locations\Models\LeaseTariff;
use App\Domain\Locations\Models\Shop;
use App\Domain\Locations\Services\LeaseTariffSync;
use App\Livewire\AppPage;
use App\Models\TaxOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;

/**
 * Secteurs, groupes de tarif et boutiques — Flask : /location-locaux/secteurs.
 * Chaque groupe de tarif créé ou modifié est synchronisé avec l'arrêté fiscal actif.
 * Un secteur, un tarif ou une boutique utilisé par un bail actif ne se supprime pas.
 */
#[Title('Secteurs & tarifs')]
class Secteurs extends AppPage
{
    public const ROUTE = 'locations.secteurs';

    public string $panel = '';   // sector | tariff | shop

    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = [];

    public function open(string $panel, ?int $id = null, ?int $parentId = null): void
    {
        $this->authorizeWrite($panel === 'shop' ? 'locations.modifier' : 'config.gerer');
        $this->panel = $panel;
        $this->editingId = $id;
        $this->resetValidation();
        $this->form = match ($panel) {
            'sector' => $id ? LeaseSector::findOrFail($id)->only(['code', 'label', 'description', 'sort_order'])
                : ['code' => '', 'label' => '', 'description' => '', 'sort_order' => LeaseSector::count() + 1],
            'tariff' => $id ? LeaseTariff::findOrFail($id)->only(['lease_sector_id', 'label', 'tariff_type', 'value', 'is_active'])
                : ['lease_sector_id' => $parentId, 'label' => '', 'tariff_type' => 'fixe', 'value' => '', 'is_active' => true],
            'shop' => $id ? Shop::findOrFail($id)->only(['lease_tariff_id', 'number', 'label', 'area', 'status', 'notes']) + ['numbers' => '']
                : ['lease_tariff_id' => $parentId, 'numbers' => '', 'number' => '', 'label' => '', 'area' => '', 'status' => 'disponible', 'notes' => ''],
        };
        $this->form = array_map(fn ($v) => is_bool($v) ? $v : (string) $v, $this->form);
        $this->showForm = true;
    }

    public function save(LeaseTariffSync $sync): void
    {
        match ($this->panel) {
            'sector' => $this->saveSector(),
            'tariff' => $this->saveTariff($sync),
            'shop' => $this->saveShop(),
        };
        $this->showForm = false;
    }

    protected function saveSector(): void
    {
        $this->authorizeWrite('config.gerer');
        $this->form['code'] = mb_strtoupper(trim($this->form['code'] ?? ''));
        if ($this->form['code'] === '' && ! $this->editingId) {
            $this->form['code'] = sprintf('SEC-%02d', LeaseSector::count() + 1);
        }
        $data = $this->validate([
            'form.code' => ['required', 'string', 'max:40', Rule::unique('lease_sectors', 'code')->ignore($this->editingId)],
            'form.label' => ['required', 'string', 'max:255'],
            'form.description' => ['nullable', 'string', 'max:1000'],
            'form.sort_order' => ['nullable', 'integer', 'min:0'],
        ], [], ['form.label' => 'libellé'])['form'];
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        $this->editingId ? LeaseSector::findOrFail($this->editingId)->update($data)
            : LeaseSector::create($data + ['tariff_type' => 'fixe', 'monthly_rate' => 0, 'unit' => 'DH/mois', 'is_active' => true]);
        $this->notify('Secteur enregistré.');
    }

    protected function saveTariff(LeaseTariffSync $sync): void
    {
        $this->authorizeWrite('config.gerer');
        $data = $this->validate([
            'form.lease_sector_id' => ['required', 'exists:lease_sectors,id'],
            'form.label' => ['required', 'string', 'max:255'],
            'form.tariff_type' => ['required', 'in:fixe,m2'],
            'form.value' => ['required', 'numeric', 'min:0'],
            'form.is_active' => ['boolean'],
        ], [], ['form.label' => 'libellé', 'form.value' => 'valeur'])['form'];
        $data['unit'] = $data['tariff_type'] === 'm2' ? 'DH/m2/mois' : 'DH/mois';

        $lt = $this->editingId ? tap(LeaseTariff::findOrFail($this->editingId))->update($data) : LeaseTariff::create($data);
        $fiscal = $sync->sync($lt->fresh());
        $this->notify($fiscal ? 'Tarif enregistré et synchronisé avec l’arrêté fiscal.' : 'Tarif enregistré (aucun arrêté ou rubrique « LOCATION_LOCAUX » pour la synchronisation).');
    }

    protected function saveShop(): void
    {
        $this->authorizeWrite('locations.modifier');
        if ($this->editingId) {
            $data = $this->validate([
                'form.number' => ['required', 'string', 'max:40'],
                'form.label' => ['nullable', 'string', 'max:255'],
                'form.area' => ['nullable', 'numeric', 'min:0'],
                'form.status' => ['required', 'in:disponible,exploitee,fermee'],
                'form.notes' => ['nullable', 'string', 'max:1000'],
            ])['form'];
            Shop::findOrFail($this->editingId)->update(['area' => (float) ($data['area'] ?: 0)] + $data);
            $this->notify('Boutique modifiée.');

            return;
        }
        $this->validate([
            'form.lease_tariff_id' => ['required', 'exists:lease_tariffs,id'],
            'form.numbers' => ['required', 'string', 'max:2000'],
            'form.area' => ['nullable', 'numeric', 'min:0'],
            'form.status' => ['required', 'in:disponible,exploitee,fermee'],
        ], [], ['form.numbers' => 'numéros']);
        // Plusieurs numéros séparés par des virgules (Flask : « numeros »).
        $numbers = collect(explode(',', $this->form['numbers']))->map(fn ($n) => trim($n))->filter()->unique();
        foreach ($numbers as $n) {
            Shop::create(['lease_tariff_id' => $this->form['lease_tariff_id'], 'number' => $n, 'label' => $this->form['label'] ?: null,
                'area' => (float) ($this->form['area'] ?: 0), 'status' => $this->form['status'], 'notes' => $this->form['notes'] ?: null]);
        }
        $this->notify($numbers->count().' boutique(s) ajoutée(s).');
    }

    public function deleteSector(int $id): void
    {
        $this->authorizeWrite('config.gerer');
        if ($n = Lease::where('lease_sector_id', $id)->where('is_active', true)->count()) {
            $this->notify("Impossible : {$n} bail(s) actif(s) dans ce secteur.", 'error');

            return;
        }
        DB::transaction(function () use ($id) {
            $tariffIds = LeaseTariff::where('lease_sector_id', $id)->pluck('id');
            Shop::whereIn('lease_tariff_id', $tariffIds)->delete();
            LeaseTariff::whereIn('id', $tariffIds)->delete();
            LeaseSector::whereKey($id)->delete();
        });
        $this->notify('Secteur supprimé.');
    }

    public function deleteTariff(int $id): void
    {
        $this->authorizeWrite('config.gerer');
        if ($n = Lease::where('lease_tariff_id', $id)->where('is_active', true)->count()) {
            $this->notify("Impossible : {$n} bail(s) utilisent ce tarif.", 'error');

            return;
        }
        DB::transaction(function () use ($id) {
            Shop::where('lease_tariff_id', $id)->delete();
            LeaseTariff::whereKey($id)->delete();
        });
        $this->notify('Tarif supprimé.');
    }

    public function deleteShop(int $id): void
    {
        $this->authorizeWrite('locations.modifier');
        if (Lease::where('shop_id', $id)->where('is_active', true)->exists()) {
            $this->notify('Impossible : un bail actif occupe cette boutique.', 'error');

            return;
        }
        Shop::whereKey($id)->delete();
        $this->notify('Boutique supprimée.');
    }

    public function render(LeaseTariffSync $sync)
    {
        $sectors = LeaseSector::with(['leaseTariffs' => fn ($q) => $q->orderBy('label')->withCount(['leases' => fn ($l) => $l->where('is_active', true)]),
            'leaseTariffs.shops' => fn ($q) => $q->orderBy('number')])
            ->orderBy('sort_order')->orderBy('label')->get();

        return view('livewire.locations.secteurs', [
            'sectors' => $sectors,
            'syncStatus' => $sectors->flatMap->leaseTariffs->mapWithKeys(fn ($t) => [$t->id => $sync->status($t)]),
            'activeOrder' => TaxOrder::where('status', 'actif')->orderByDesc('id')->first(),
        ]);
    }
}
