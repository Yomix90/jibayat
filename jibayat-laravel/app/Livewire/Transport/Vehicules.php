<?php

namespace App\Livewire\Transport;

use App\Domain\Caisse\Models\TaxDeclaration;
use App\Domain\Transport\Models\Vehicle;
use App\Domain\Transport\Services\TransportCalculator;
use App\Livewire\AppPage;
use App\Models\Municipality;
use App\Models\Taxpayer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

/**
 * Véhicules (stationnement + TPV) — Flask /stationnement : liste, indicateurs, ajout, modification, suppression.
 * Le type du véhicule est le libellé d'un tarif de stationnement de l'arrêté fiscal actif.
 */
#[Title('Véhicules — Transport')]
class Vehicules extends AppPage
{
    public const ROUTE = 'transport.vehicules';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'situation')]
    public string $situation = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = [];

    public bool $newTaxpayer = false;

    public array $taxpayerForm = ['last_name' => '', 'first_name' => '', 'last_name_ar' => '', 'company_name' => '', 'cin' => '', 'phone' => ''];

    public string $taxpayerSearch = '';

    protected function emptyForm(): array
    {
        return ['number' => '', 'taxpayer_id' => '', 'registration' => '', 'type' => '', 'authorization_number' => '', 'authorized_on' => '',
            'seats' => '', 'tax_start_quarter' => (string) now()->quarter, 'tax_start_year' => (string) now()->year, 'status' => 'actif'];
    }

    public static function nextNumber(): string
    {
        $max = (int) Vehicle::whereRaw("number REGEXP '^[0-9]+$'")->max(DB::raw('CAST(number AS UNSIGNED)'));

        return (string) ($max > 0 ? $max + 1 : Vehicle::count() + 1);
    }

    public function create(): void
    {
        $this->authorizeWrite('transport.ajouter');
        $this->reset('editingId', 'newTaxpayer', 'taxpayerSearch');
        $this->taxpayerForm = array_map(fn () => '', $this->taxpayerForm);
        $this->form = ['number' => self::nextNumber(), 'type' => (string) $this->types()->first()] + $this->emptyForm();
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorizeWrite('transport.modifier');
        $v = Vehicle::findOrFail($id);
        $this->editingId = $v->id;
        $this->newTaxpayer = false;
        $this->form = collect($this->emptyForm())->mapWithKeys(fn ($x, $k) => [$k => match ($k) {
            'authorized_on' => $v->authorized_on?->toDateString() ?? '',
            default => (string) ($v->{$k} ?? ''),
        }])->all();
        $this->resetValidation();
        $this->showForm = true;
    }

    /** Types proposés : libellés des tarifs de stationnement (et TPV) de l'arrêté actif. */
    protected function types(): Collection
    {
        $calc = app(TransportCalculator::class);

        return $calc->tariffs(TransportCalculator::MODULE)->pluck('label')
            ->merge($calc->tariffs(TransportCalculator::TPV)->pluck('label'))->unique()->values();
    }

    public function save(): void
    {
        $this->authorizeWrite($this->editingId ? 'transport.modifier' : 'transport.ajouter');

        if ($this->newTaxpayer && ! $this->editingId) {
            $t = $this->validate([
                'taxpayerForm.last_name' => ['required_without:taxpayerForm.company_name', 'nullable', 'string', 'max:255'],
                'taxpayerForm.company_name' => ['nullable', 'string', 'max:255'],
                'taxpayerForm.first_name' => ['nullable', 'string', 'max:255'],
                'taxpayerForm.last_name_ar' => ['nullable', 'string', 'max:255'],
                'taxpayerForm.cin' => ['nullable', 'string', 'max:30'],
                'taxpayerForm.phone' => ['nullable', 'string', 'max:40'],
            ], ['taxpayerForm.last_name.required_without' => 'Indiquez le nom ou la raison sociale.'])['taxpayerForm'];
            $taxpayer = Taxpayer::create(array_map(fn ($v) => $v === '' ? null : $v, $t) + [
                'number' => 'CTB-'.(Taxpayer::max('id') + 1), 'person_type' => $t['company_name'] ? 'morale' : 'physique',
                'is_active' => true, 'municipality_id' => Municipality::value('id'),
            ]);
            $this->form['taxpayer_id'] = (string) $taxpayer->id;
            $this->newTaxpayer = false;
        }

        $data = $this->validate([
            'form.number' => ['required', 'string', 'max:40', Rule::unique('vehicles', 'number')->ignore($this->editingId)],
            'form.taxpayer_id' => [$this->editingId ? 'nullable' : 'required', 'exists:taxpayers,id'],
            'form.registration' => ['required', 'string', 'max:40'],
            'form.type' => ['required', 'string', 'max:60'],
            'form.authorization_number' => ['nullable', 'string', 'max:60'],
            'form.authorized_on' => ['nullable', 'date'],
            'form.seats' => ['nullable', 'integer', 'min:0', 'max:200'],
            'form.tax_start_quarter' => ['required', 'integer', 'between:1,4'],
            'form.tax_start_year' => ['required', 'integer', 'min:2000', 'max:'.(now()->year + 1)],
            'form.status' => ['required', Rule::in(['actif', 'suspendu', 'retire'])],
        ], [], ['form.number' => 'n°', 'form.taxpayer_id' => 'propriétaire', 'form.registration' => 'immatriculation',
            'form.type' => 'type de véhicule', 'form.tax_start_year' => 'année de début'])['form'];
        $data = array_map(fn ($v) => $v === '' ? null : $v, $data);
        $data['seats'] ??= 0;
        $data['registration'] = mb_strtoupper(trim($data['registration']));

        if ($this->editingId) {
            unset($data['taxpayer_id']);
            Vehicle::findOrFail($this->editingId)->update($data);
            $this->notify('Véhicule modifié.');
        } else {
            Vehicle::create($data + ['is_active' => true, 'municipality_id' => Municipality::value('id')]);
            $this->notify("Véhicule n° {$data['number']} ({$data['registration']}) enregistré.");
        }
        $this->showForm = false;
    }

    /** Flask sta_supprimer : refusé si un trimestre est payé ; sinon retiré de la liste. */
    public function delete(int $id): void
    {
        $this->authorizeWrite('transport.supprimer');
        $v = Vehicle::findOrFail($id);
        $paid = TaxDeclaration::where('taxable_type', 'vehicle')->where('taxable_id', $id)->where('status', 'paye')->count();
        if ($paid) {
            $this->notify("Impossible : {$paid} déclaration(s) payée(s) liée(s) à ce véhicule.", 'error');

            return;
        }
        $v->update(['is_active' => false]);
        activity('parametres')->causedBy(auth()->user())->performedOn($v)->log("Suppression (archivage) du véhicule {$v->registration}");
        $this->notify("Véhicule {$v->registration} retiré de la liste.");
    }

    public static function rows(TransportCalculator $calc, string $search = ''): Collection
    {
        return Vehicle::with('taxpayer')->where('is_active', true)
            ->when($search, function ($q) use ($search) {
                $s = "%{$search}%";
                $q->where(fn ($w) => $w->where('registration', 'like', $s)->orWhere('number', 'like', $s)
                    ->orWhereHas('taxpayer', fn ($t) => $t->where('last_name', 'like', $s)->orWhere('last_name_ar', 'like', $s)
                        ->orWhere('first_name', 'like', $s)->orWhere('first_name_ar', 'like', $s)->orWhere('company_name', 'like', $s)
                        ->orWhere('company_name_ar', 'like', $s)->orWhere('cin', 'like', $s)));
            })
            ->latest()->get()->map(fn (Vehicle $v) => ['vehicle' => $v] + $calc->situation($v));
    }

    public function render(TransportCalculator $calc)
    {
        $all = self::rows($calc, $this->search);
        $kpi = [
            'total' => $all->count(),
            'unpaid' => $all->where('nb_non_declares', '>', 0)->count(),
            'pending' => $all->where('nb_non_declares', 0)->where('nb_emises', '>', 0)->count(),
            'up_to_date' => $all->where('nb_non_paye', 0)->count(),
            'amount_due' => $all->sum('montant_impaye'),
            'amount_pending' => $all->sum('montant_en_attente'),
        ];
        $rows = $all->filter(fn ($r) => match ($this->situation) {
            'impaye' => $r['nb_non_declares'] > 0, 'en_attente' => $r['nb_emises'] > 0, 'ajour' => $r['nb_non_paye'] === 0, default => true,
        });
        $s = $this->taxpayerSearch;
        $taxpayers = $this->showForm ? Taxpayer::where('is_active', true)
            ->when($s, fn ($q) => $q->where(fn ($w) => $w->where('last_name', 'like', "%{$s}%")->orWhere('first_name', 'like', "%{$s}%")
                ->orWhere('company_name', 'like', "%{$s}%")->orWhere('cin', 'like', "%{$s}%")))
            ->orWhere('id', $this->form['taxpayer_id'] ?? 0)->orderBy('last_name')->limit(50)->get() : collect();

        return view('livewire.transport.vehicules', ['rows' => $rows, 'kpi' => $kpi, 'types' => $this->types(), 'taxpayers' => $taxpayers]);
    }
}
