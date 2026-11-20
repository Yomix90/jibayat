<?php

namespace App\Livewire\Locations;

use App\Domain\Locations\Models\Lease;
use App\Domain\Locations\Models\LeaseSector;
use App\Domain\Locations\Models\LeaseTariff;
use App\Domain\Locations\Models\Shop;
use App\Domain\Locations\Services\LeaseCalculator;
use App\Livewire\AppPage;
use App\Models\Municipality;
use App\Models\Taxpayer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

/**
 * Locataires (baux) — Flask : /location-locaux (liste, indicateurs, ajout, modification, suppression).
 */
#[Title('Locataires')]
class Locataires extends AppPage
{
    public const ROUTE = 'locations.locataires';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'secteur')]
    public string $sector = '';

    #[Url(as: 'situation')]
    public string $situation = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = [];

    // Ajout rapide d'un contribuable depuis le formulaire du bail
    public bool $newTaxpayer = false;

    public array $taxpayerForm = ['last_name' => '', 'first_name' => '', 'cin' => '', 'phone' => ''];

    public string $taxpayerSearch = '';

    protected function emptyForm(): array
    {
        return ['number' => '', 'taxpayer_id' => '', 'shop_id' => '', 'lease_sector_id' => '', 'lease_tariff_id' => '',
            'premises_ref' => '', 'address' => '', 'area' => '', 'monthly_rent' => '', 'starts_on' => now()->startOfMonth()->toDateString(),
            'ends_on' => '', 'status' => 'actif'];
    }

    /** Prochain n° de bail : plus grand n° numérique + 1 (Flask get_next_seq_num). */
    public static function nextNumber(): string
    {
        $max = (int) Lease::whereRaw("number REGEXP '^[0-9]+$'")->max(DB::raw('CAST(number AS UNSIGNED)'));

        return (string) ($max > 0 ? $max + 1 : Lease::count() + 1);
    }

    public function create(): void
    {
        $this->authorizeWrite('locations.ajouter');
        $this->reset('editingId', 'newTaxpayer', 'taxpayerSearch');
        $this->taxpayerForm = ['last_name' => '', 'first_name' => '', 'cin' => '', 'phone' => ''];
        $this->form = ['number' => self::nextNumber()] + $this->emptyForm();
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorizeWrite('locations.modifier');
        $l = Lease::findOrFail($id);
        $this->editingId = $l->id;
        $this->newTaxpayer = false;
        $this->form = collect($this->emptyForm())->mapWithKeys(fn ($v, $k) => [$k => match (true) {
            in_array($k, ['starts_on', 'ends_on']) => $l->{$k}?->toDateString() ?? '',
            in_array($k, ['area', 'monthly_rent']) => $l->{$k} !== null ? (string) (float) $l->{$k} : '',
            default => (string) ($l->{$k} ?? ''),
        }])->all();
        $this->resetValidation();
        $this->showForm = true;
    }

    public function updatedFormShopId($value): void
    {
        if ($value && ($shop = Shop::with('leaseTariff')->find($value))) {
            $this->form['lease_tariff_id'] = (string) $shop->lease_tariff_id;
            $this->form['lease_sector_id'] = (string) $shop->leaseTariff->lease_sector_id;
            $this->form['premises_ref'] = $this->form['premises_ref'] ?: $shop->number;
            if (! (float) ($this->form['area'] ?: 0)) {
                $this->form['area'] = (string) (float) $shop->area;
            }
        }
    }

    public function save(): void
    {
        $this->authorizeWrite($this->editingId ? 'locations.modifier' : 'locations.ajouter');

        if ($this->newTaxpayer && ! $this->editingId) {
            $t = $this->validate([
                'taxpayerForm.last_name' => ['required', 'string', 'max:255'],
                'taxpayerForm.first_name' => ['nullable', 'string', 'max:255'],
                'taxpayerForm.cin' => ['nullable', 'string', 'max:30'],
                'taxpayerForm.phone' => ['nullable', 'string', 'max:40'],
            ], [], ['taxpayerForm.last_name' => 'nom du locataire'])['taxpayerForm'];
            $taxpayer = Taxpayer::create(array_map(fn ($v) => $v === '' ? null : $v, $t) + [
                'number' => 'CTB-'.(Taxpayer::max('id') + 1), 'person_type' => 'physique', 'is_active' => true,
                'municipality_id' => Municipality::value('id'),
            ]);
            $this->form['taxpayer_id'] = (string) $taxpayer->id;
            $this->newTaxpayer = false;
        }

        $data = $this->validate([
            'form.number' => ['required', 'string', 'max:40', Rule::unique('leases', 'number')->ignore($this->editingId)],
            'form.taxpayer_id' => ['required', 'exists:taxpayers,id'],
            'form.shop_id' => ['nullable', 'exists:shops,id'],
            'form.lease_sector_id' => ['nullable', 'exists:lease_sectors,id'],
            'form.lease_tariff_id' => ['nullable', 'exists:lease_tariffs,id'],
            'form.premises_ref' => ['nullable', 'string', 'max:255'],
            'form.address' => ['nullable', 'string', 'max:255'],
            'form.area' => ['nullable', 'numeric', 'min:0'],
            'form.monthly_rent' => ['nullable', 'numeric', 'min:0'],
            'form.starts_on' => ['required', 'date'],
            'form.ends_on' => ['nullable', 'date', 'after_or_equal:form.starts_on'],
            'form.status' => ['required', Rule::in(['actif', 'suspendu', 'resilie'])],
        ], [], ['form.number' => 'n° de bail', 'form.taxpayer_id' => 'locataire', 'form.starts_on' => 'date de début'])['form'];
        $data = array_map(fn ($v) => $v === '' ? null : $v, $data);
        $data['area'] ??= 0;
        $data['monthly_rent'] ??= 0;

        DB::transaction(function () use ($data) {
            if ($this->editingId) {
                Lease::findOrFail($this->editingId)->update($data);
            } else {
                $lease = Lease::create($data + ['is_active' => true, 'municipality_id' => Municipality::value('id')]);
                if ($lease->shop_id) {
                    Shop::whereKey($lease->shop_id)->update(['status' => 'exploitee']);
                }
            }
        });
        $this->notify($this->editingId ? 'Bail modifié.' : "Bail n° {$data['number']} ajouté.");
        $this->showForm = false;
    }

    /** Flask loc_supprimer : bail avec bulletins ➜ archivé (résilié) ; sinon supprimé et boutique libérée. */
    public function delete(int $id): void
    {
        $this->authorizeWrite('locations.supprimer');
        $lease = Lease::findOrFail($id);
        if ($lease->declarations()->exists()) {
            $lease->update(['is_active' => false, 'status' => 'resilie']);
            $this->notify("Bail {$lease->number} archivé (résilié) : ses pièces sont conservées.");

            return;
        }
        DB::transaction(function () use ($lease) {
            if ($lease->shop_id) {
                Shop::whereKey($lease->shop_id)->update(['status' => 'disponible']);
            }
            $lease->delete();
        });
        $this->notify("Bail {$lease->number} supprimé.");
    }

    public function render(LeaseCalculator $calc)
    {
        $leases = Lease::with(['taxpayer', 'shop', 'leaseTariff', 'leaseSector'])->where('is_active', true)
            ->when($this->sector, fn ($q) => $q->where('lease_sector_id', $this->sector))
            ->when($this->search, function ($q) {
                $s = "%{$this->search}%";
                $q->where(fn ($w) => $w->where('number', 'like', $s)->orWhere('premises_ref', 'like', $s)
                    ->orWhereHas('taxpayer', fn ($t) => $t->where('last_name', 'like', $s)->orWhere('first_name', 'like', $s)
                        ->orWhere('last_name_ar', 'like', $s)->orWhere('company_name', 'like', $s)->orWhere('cin', 'like', $s))
                    ->orWhereHas('shop', fn ($b) => $b->where('number', 'like', $s)));
            })
            ->orderByDesc('created_at')->get()
            ->map(fn (Lease $l) => ['lease' => $l, 'rent' => $calc->monthlyRent($l)] + $calc->situation($l));

        $kpi = [
            'total' => $leases->count(),
            'unpaid' => $leases->where('nb_non_declares', '>', 0)->count(),
            'pending' => $leases->where('nb_non_declares', 0)->where('nb_emises', '>', 0)->count(),
            'up_to_date' => $leases->where('nb_non_paye', 0)->count(),
            'amount_due' => $leases->sum('total_du'),
            'amount_pending' => $leases->sum('total_en_attente'),
            'free_shops' => Shop::where('status', 'disponible')->count(),
        ];

        $rows = $leases->filter(fn ($r) => match ($this->situation) {
            'impaye' => $r['nb_non_declares'] > 0,
            'en_attente' => $r['nb_emises'] > 0,
            'ajour' => $r['nb_non_paye'] === 0,
            default => true,
        });

        $taxpayers = Taxpayer::where('is_active', true)
            ->when($this->taxpayerSearch, fn ($q) => $q->where(fn ($w) => $w->where('last_name', 'like', "%{$this->taxpayerSearch}%")
                ->orWhere('first_name', 'like', "%{$this->taxpayerSearch}%")->orWhere('cin', 'like', "%{$this->taxpayerSearch}%")
                ->orWhere('company_name', 'like', "%{$this->taxpayerSearch}%")))
            ->orWhere('id', $this->form['taxpayer_id'] ?? 0)
            ->orderBy('last_name')->limit(50)->get();

        return view('livewire.locations.locataires', [
            'rows' => $rows,
            'kpi' => $kpi,
            'sectors' => LeaseSector::where('is_active', true)->orderBy('sort_order')->orderBy('label')->get(),
            'tariffs' => LeaseTariff::where('is_active', true)->when($this->form['lease_sector_id'] ?? null, fn ($q, $s) => $q->where('lease_sector_id', $s))->orderBy('label')->get(),
            'freeShops' => Shop::with('leaseTariff.leaseSector')->where(fn ($q) => $q->where('status', 'disponible')->orWhere('id', $this->form['shop_id'] ?? 0))->orderBy('number')->get(),
            'taxpayers' => $taxpayers,
        ]);
    }
}
