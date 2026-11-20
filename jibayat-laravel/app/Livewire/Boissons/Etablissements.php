<?php

namespace App\Livewire\Boissons;

use App\Domain\Boissons\Models\BeverageOutlet;
use App\Domain\Boissons\Services\BeverageCalculator;
use App\Domain\Caisse\Models\TaxDeclaration;
use App\Livewire\AppPage;
use App\Models\Municipality;
use App\Models\Taxpayer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

/**
 * Établissements (débits de boissons) — Flask /debits-boissons : liste, indicateurs, ajout, modification, suppression.
 */
#[Title('Établissements — Débits de boissons')]
class Etablissements extends AppPage
{
    public const ROUTE = 'boissons.etablissements';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'statut')]
    public string $status = '';

    #[Url(as: 'situation')]
    public string $situation = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = [];

    public bool $newTaxpayer = false;

    public array $taxpayerForm = ['last_name' => '', 'first_name' => '', 'last_name_ar' => '', 'company_name' => '', 'cin' => '', 'rc' => '', 'phone' => ''];

    public string $taxpayerSearch = '';

    protected function emptyForm(): array
    {
        return ['number' => '', 'taxpayer_id' => '', 'name' => '', 'tariff_id' => '', 'address' => '', 'area' => '',
            'authorization_number' => '', 'authorized_on' => '', 'tax_start_quarter' => (string) now()->quarter,
            'tax_start_year' => (string) now()->year, 'status' => 'actif'];
    }

    public static function nextNumber(): string
    {
        $max = (int) BeverageOutlet::whereRaw("number REGEXP '^[0-9]+$'")->max(DB::raw('CAST(number AS UNSIGNED)'));

        return (string) ($max > 0 ? $max + 1 : BeverageOutlet::count() + 1);
    }

    public function create(): void
    {
        $this->authorizeWrite('boissons.ajouter');
        $this->reset('editingId', 'newTaxpayer', 'taxpayerSearch');
        $this->taxpayerForm = array_map(fn () => '', $this->taxpayerForm);
        $this->form = ['number' => self::nextNumber()] + $this->emptyForm();
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorizeWrite('boissons.modifier');
        $o = BeverageOutlet::findOrFail($id);
        $this->editingId = $o->id;
        $this->newTaxpayer = false;
        $this->form = collect($this->emptyForm())->mapWithKeys(fn ($v, $k) => [$k => match ($k) {
            'authorized_on' => $o->authorized_on?->toDateString() ?? '',
            'area' => $o->area !== null ? (string) (float) $o->area : '',
            default => (string) ($o->{$k} ?? ''),
        }])->all();
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorizeWrite($this->editingId ? 'boissons.modifier' : 'boissons.ajouter');

        if ($this->newTaxpayer && ! $this->editingId) {
            $t = $this->validate([
                'taxpayerForm.last_name' => ['required_without:taxpayerForm.company_name', 'nullable', 'string', 'max:255'],
                'taxpayerForm.company_name' => ['nullable', 'string', 'max:255'],
                'taxpayerForm.first_name' => ['nullable', 'string', 'max:255'],
                'taxpayerForm.last_name_ar' => ['nullable', 'string', 'max:255'],
                'taxpayerForm.cin' => ['nullable', 'string', 'max:30'],
                'taxpayerForm.rc' => ['nullable', 'string', 'max:30'],
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
            'form.number' => ['required', 'string', 'max:40', Rule::unique('beverage_outlets', 'number')->ignore($this->editingId)],
            'form.taxpayer_id' => [$this->editingId ? 'nullable' : 'required', 'exists:taxpayers,id'],
            'form.name' => ['required', 'string', 'max:255'],
            'form.tariff_id' => ['required', Rule::in($this->tariffs()->pluck('id')->map(fn ($id) => (string) $id)->all())],
            'form.address' => ['nullable', 'string', 'max:255'],
            'form.area' => ['nullable', 'numeric', 'min:0'],
            'form.authorization_number' => ['nullable', 'string', 'max:60'],
            'form.authorized_on' => ['nullable', 'date'],
            'form.tax_start_quarter' => ['required', 'integer', 'between:1,4'],
            'form.tax_start_year' => ['required', 'integer', 'min:2000', 'max:'.(now()->year + 1)],
            'form.status' => ['required', Rule::in(['actif', 'cesse'])],
        ], [], ['form.number' => 'n° d’établissement', 'form.taxpayer_id' => 'exploitant', 'form.name' => 'nom de l’établissement',
            'form.tariff_id' => 'type (tarif)', 'form.tax_start_year' => 'année de début'])['form'];
        $data = array_map(fn ($v) => $v === '' ? null : $v, $data);
        $data['area'] ??= 0;
        $data['type'] = $this->tariffs()->firstWhere('id', (int) $data['tariff_id'])?->label;

        if ($this->editingId) {
            unset($data['taxpayer_id']);
            BeverageOutlet::findOrFail($this->editingId)->update($data);
            $this->notify('Établissement modifié.');
        } else {
            BeverageOutlet::create($data + ['is_active' => true, 'municipality_id' => Municipality::value('id')]);
            $this->notify("Établissement n° {$data['number']} ajouté.");
        }
        $this->showForm = false;
    }

    /** Flask tdb_supprimer : refusé s'il existe une déclaration payée ; sinon retiré de la liste (actif = 0). */
    public function delete(int $id): void
    {
        $this->authorizeWrite('boissons.supprimer');
        $outlet = BeverageOutlet::findOrFail($id);
        $paid = TaxDeclaration::where('taxable_type', 'beverage_outlet')->where('taxable_id', $id)->where('status', 'paye')->count();
        if ($paid) {
            $this->notify("Impossible : {$paid} déclaration(s) payée(s) liée(s) à cet établissement.", 'error');

            return;
        }
        $outlet->update(['is_active' => false]);
        activity('parametres')->causedBy(auth()->user())->performedOn($outlet)->log("Suppression (archivage) de l'établissement {$outlet->number}");
        $this->notify("Établissement n° {$outlet->number} retiré de la liste.");
    }

    protected function tariffs(): Collection
    {
        return app(BeverageCalculator::class)->tariffs();
    }

    /** Liste + indicateurs (Flask _get_tdb_items_data), partagée avec l'impression. */
    public static function rows(BeverageCalculator $calc, string $search = '', string $status = ''): Collection
    {
        return BeverageOutlet::with(['taxpayer', 'tariff'])->where('is_active', true)
            ->when(in_array($status, ['actif', 'cesse'], true), fn ($q) => $q->where('status', $status))
            ->when($search, function ($q) use ($search) {
                $s = "%{$search}%";
                $q->where(fn ($w) => $w->where('number', 'like', $s)->orWhere('name', 'like', $s)
                    ->orWhereHas('taxpayer', fn ($t) => $t->where('last_name', 'like', $s)->orWhere('last_name_ar', 'like', $s)
                        ->orWhere('first_name', 'like', $s)->orWhere('first_name_ar', 'like', $s)->orWhere('company_name', 'like', $s)
                        ->orWhere('company_name_ar', 'like', $s)->orWhere('cin', 'like', $s)));
            })
            ->latest()->get()
            ->map(fn (BeverageOutlet $o) => ['outlet' => $o] + $calc->situation($o));
    }

    public function render(BeverageCalculator $calc)
    {
        $all = self::rows($calc, $this->search, $this->status);
        $kpi = [
            'total' => $all->count(),
            'unpaid' => $all->where('nb_non_declares', '>', 0)->count(),
            'pending' => $all->where('nb_non_declares', 0)->where('nb_emises', '>', 0)->count(),
            'up_to_date' => $all->where('nb_non_paye', 0)->count(),
            'amount_late' => $all->sum('montant_estime_retard'),
            'amount_pending' => $all->sum('montant_en_attente'),
        ];
        $rows = $all->filter(fn ($r) => match ($this->situation) {
            'impaye' => $r['nb_non_declares'] > 0, 'en_attente' => $r['nb_emises'] > 0, 'ajour' => $r['nb_non_paye'] === 0, default => true,
        });

        $s = $this->taxpayerSearch;
        $taxpayers = $this->showForm ? Taxpayer::where('is_active', true)
            ->when($s, fn ($q) => $q->where(fn ($w) => $w->where('last_name', 'like', "%{$s}%")->orWhere('first_name', 'like', "%{$s}%")
                ->orWhere('company_name', 'like', "%{$s}%")->orWhere('cin', 'like', "%{$s}%")->orWhere('rc', 'like', "%{$s}%")))
            ->orWhere('id', $this->form['taxpayer_id'] ?? 0)->orderBy('last_name')->limit(50)->get() : collect();

        return view('livewire.boissons.etablissements', ['rows' => $rows, 'kpi' => $kpi, 'tariffs' => $this->tariffs(), 'taxpayers' => $taxpayers]);
    }
}
