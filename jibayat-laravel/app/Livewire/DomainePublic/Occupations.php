<?php

namespace App\Livewire\DomainePublic;

use App\Domain\Caisse\Models\TaxDeclaration;
use App\Domain\DomainePublic\Models\PublicOccupation;
use App\Domain\DomainePublic\Services\OccupationCalculator;
use App\Livewire\AppPage;
use App\Models\Municipality;
use App\Models\Taxpayer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

/**
 * Occupations du domaine public — Flask /occupation-domaine : liste, indicateurs, ajout, modification, archivage.
 * Le type d'occupation est un tarif de l'arrêté fiscal (montant = tarif × superficie).
 */
#[Title('Occupations — Domaine public')]
class Occupations extends AppPage
{
    public const ROUTE = 'domaine-public.occupations';

    public const STATUSES = ['actif' => 'En cours', 'suspendu' => 'Suspendu', 'expire' => 'Expiré'];

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
        return ['number' => '', 'taxpayer_id' => '', 'type' => '', 'location' => '', 'area' => '', 'authorization_number' => '',
            'starts_on' => today()->toDateString(), 'ends_on' => '', 'tax_start_quarter' => (string) now()->quarter,
            'tax_start_year' => (string) now()->year, 'status' => 'actif'];
    }

    public static function nextNumber(): string
    {
        $max = (int) PublicOccupation::whereRaw("number REGEXP '^[0-9]+$'")->max(DB::raw('CAST(number AS UNSIGNED)'));

        return (string) ($max > 0 ? $max + 1 : PublicOccupation::count() + 1);
    }

    public function create(): void
    {
        $this->authorizeWrite('domaine_public.ajouter');
        $this->reset('editingId', 'newTaxpayer', 'taxpayerSearch');
        $this->taxpayerForm = array_map(fn () => '', $this->taxpayerForm);
        $this->form = ['number' => self::nextNumber(), 'type' => (string) app(OccupationCalculator::class)->tariffs()->first()?->label] + $this->emptyForm();
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorizeWrite('domaine_public.modifier');
        $o = PublicOccupation::findOrFail($id);
        $this->editingId = $o->id;
        $this->newTaxpayer = false;
        $this->form = collect($this->emptyForm())->mapWithKeys(fn ($v, $k) => [$k => match ($k) {
            'starts_on', 'ends_on' => $o->{$k}?->toDateString() ?? '',
            'area' => $o->area !== null ? (string) (float) $o->area : '',
            default => (string) ($o->{$k} ?? ''),
        }])->all();
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorizeWrite($this->editingId ? 'domaine_public.modifier' : 'domaine_public.ajouter');
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
            'form.number' => ['required', 'string', 'max:40', Rule::unique('public_occupations', 'number')->ignore($this->editingId)],
            'form.taxpayer_id' => [$this->editingId ? 'nullable' : 'required', 'exists:taxpayers,id'],
            'form.type' => ['required', 'string', 'max:60'],
            'form.location' => ['required', 'string', 'max:255'],
            'form.area' => ['required', 'numeric', 'min:0.01'],
            'form.authorization_number' => ['nullable', 'string', 'max:60'],
            'form.starts_on' => ['nullable', 'date'],
            'form.ends_on' => ['nullable', 'date', 'after_or_equal:form.starts_on'],
            'form.tax_start_quarter' => ['required', 'integer', 'between:1,4'],
            'form.tax_start_year' => ['required', 'integer', 'min:2000', 'max:'.(now()->year + 1)],
            'form.status' => ['required', Rule::in(array_keys(self::STATUSES))],
        ], [], ['form.number' => 'n°', 'form.taxpayer_id' => 'occupant', 'form.type' => "type d'occupation", 'form.location' => 'emplacement',
            'form.area' => 'superficie', 'form.tax_start_year' => 'année de début'])['form'];
        $data = array_map(fn ($v) => $v === '' ? null : $v, $data);

        if ($this->editingId) {
            unset($data['taxpayer_id']);
            PublicOccupation::findOrFail($this->editingId)->update($data);
            $this->notify('Occupation modifiée.');
        } else {
            PublicOccupation::create($data + ['is_active' => true, 'municipality_id' => Municipality::value('id')]);
            $this->notify("Occupation n° {$data['number']} enregistrée.");
        }
        $this->showForm = false;
    }

    /** Flask odp_supprimer : archivage. Refusé ici si une période est payée (les pièces doivent rester rattachées). */
    public function delete(int $id): void
    {
        $this->authorizeWrite('domaine_public.supprimer');
        $o = PublicOccupation::findOrFail($id);
        if (TaxDeclaration::where('taxable_type', 'public_occupation')->where('taxable_id', $id)->where('status', 'paye')->exists()) {
            $this->notify('Occupation avec des périodes payées : passez-la en « Expiré » au lieu de la supprimer.', 'error');

            return;
        }
        $o->update(['is_active' => false]);
        activity('parametres')->causedBy(auth()->user())->performedOn($o)->log("Archivage de l'occupation {$o->number}");
        $this->notify("Occupation n° {$o->number} archivée.");
    }

    public static function rows(OccupationCalculator $calc, string $search = ''): Collection
    {
        return PublicOccupation::with('taxpayer')->where('is_active', true)
            ->when($search, function ($q) use ($search) {
                $s = "%{$search}%";
                $q->where(fn ($w) => $w->where('number', 'like', $s)->orWhere('location', 'like', $s)->orWhere('type', 'like', $s)
                    ->orWhereHas('taxpayer', fn ($t) => $t->where('last_name', 'like', $s)->orWhere('last_name_ar', 'like', $s)
                        ->orWhere('first_name', 'like', $s)->orWhere('company_name', 'like', $s)->orWhere('cin', 'like', $s)));
            })
            ->latest()->get()->map(fn (PublicOccupation $o) => ['occupation' => $o] + $calc->situation($o));
    }

    public function render(OccupationCalculator $calc)
    {
        $all = self::rows($calc, $this->search);
        $kpi = [
            'total' => $all->count(),
            'unpaid' => $all->where('nb_non_declares', '>', 0)->count(),
            'pending' => $all->where('nb_non_declares', 0)->where('nb_emises', '>', 0)->count(),
            'expired' => $all->where('est_expire', true)->count(),
            'amount_due' => $all->sum('montant_impaye'),
            'amount_pending' => $all->sum('montant_en_attente'),
        ];
        $rows = $all->filter(fn ($r) => match ($this->situation) {
            'impaye' => $r['nb_non_declares'] > 0, 'en_attente' => $r['nb_emises'] > 0, 'ajour' => $r['nb_impayes'] === 0,
            'expire' => $r['est_expire'], default => true,
        });
        $s = $this->taxpayerSearch;
        $taxpayers = $this->showForm ? Taxpayer::where('is_active', true)
            ->when($s, fn ($q) => $q->where(fn ($w) => $w->where('last_name', 'like', "%{$s}%")->orWhere('first_name', 'like', "%{$s}%")
                ->orWhere('company_name', 'like', "%{$s}%")->orWhere('cin', 'like', "%{$s}%")))
            ->orWhere('id', $this->form['taxpayer_id'] ?? 0)->orderBy('last_name')->limit(50)->get() : collect();

        return view('livewire.domaine-public.occupations', ['rows' => $rows, 'kpi' => $kpi, 'tariffs' => $calc->tariffs(), 'taxpayers' => $taxpayers]);
    }
}
