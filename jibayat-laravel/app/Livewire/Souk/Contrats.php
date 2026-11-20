<?php

namespace App\Livewire\Souk;

use App\Domain\Caisse\Models\TaxDeclaration;
use App\Domain\Souk\Models\SoukContract;
use App\Domain\Souk\Services\ContractService;
use App\Domain\Souk\Services\SoukCalculator;
use App\Livewire\AppPage;
use App\Models\Municipality;
use App\Models\Taxpayer;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

/**
 * Contrats d'affermage — Flask /souks : liste, indicateurs, ajout, modification, archivage.
 */
#[Title("Contrats d'affermage — Souk")]
class Contrats extends AppPage
{
    public const ROUTE = 'souk.contrats';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'situation')]
    public string $situation = '';

    #[Url(as: 'souk')]
    public string $souk = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = [];

    public bool $newTaxpayer = false;

    public array $taxpayerForm = ['last_name' => '', 'first_name' => '', 'last_name_ar' => '', 'company_name' => '', 'cin' => '', 'phone' => ''];

    public string $taxpayerSearch = '';

    protected function emptyForm(): array
    {
        return ['number' => '', 'taxpayer_id' => '', 'souk_name' => '', 'pitch_number' => '', 'activity' => '', 'monthly_fee' => '',
            'starts_on' => today()->startOfMonth()->toDateString(), 'ends_on' => '', 'duration_years' => '1', 'increase_rate' => '5', 'status' => 'actif'];
    }

    public function create(): void
    {
        $this->authorizeWrite('souk.ajouter');
        $this->reset('editingId', 'newTaxpayer', 'taxpayerSearch');
        $this->taxpayerForm = array_map(fn () => '', $this->taxpayerForm);
        $this->form = ['number' => ContractService::nextNumber()] + $this->emptyForm();
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorizeWrite('souk.modifier');
        $c = SoukContract::findOrFail($id);
        $this->editingId = $c->id;
        $this->newTaxpayer = false;
        $this->form = collect($this->emptyForm())->mapWithKeys(fn ($v, $k) => [$k => match ($k) {
            'starts_on', 'ends_on' => $c->{$k}?->toDateString() ?? '',
            'monthly_fee' => (string) SoukCalculator::monthlyFee($c),
            'increase_rate' => (string) (float) $c->increase_rate,
            default => (string) ($c->{$k} ?? ''),
        }])->all();
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorizeWrite($this->editingId ? 'souk.modifier' : 'souk.ajouter');
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
            'form.number' => ['required', 'string', 'max:40', Rule::unique('souk_contracts', 'number')->ignore($this->editingId)],
            'form.taxpayer_id' => [$this->editingId ? 'nullable' : 'required', 'exists:taxpayers,id'],
            'form.souk_name' => ['required', 'string', 'max:255'],
            'form.pitch_number' => ['nullable', 'string', 'max:40'],
            'form.activity' => ['nullable', 'string', 'max:255'],
            'form.monthly_fee' => ['required', 'numeric', 'min:0'],
            'form.starts_on' => ['required', 'date'],
            'form.ends_on' => ['nullable', 'date', 'after_or_equal:form.starts_on'],
            'form.duration_years' => ['required', 'integer', 'min:1', 'max:20'],
            'form.increase_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'form.status' => ['required', Rule::in(array_keys(SoukContract::STATUSES))],
        ], [], ['form.number' => 'n° de contrat', 'form.taxpayer_id' => 'fermier', 'form.souk_name' => 'souk',
            'form.monthly_fee' => 'redevance mensuelle', 'form.starts_on' => 'date de début', 'form.duration_years' => 'durée'])['form'];
        $data = array_map(fn ($v) => $v === '' ? null : $v, $data);
        $data['monthly_fee'] = round((float) $data['monthly_fee'], 2);
        $data['annual_fee'] = round($data['monthly_fee'] * 12, 2);
        $data['ends_on'] ??= ContractService::defaultEnd($data['starts_on'], (int) $data['duration_years']);

        if ($this->editingId) {
            unset($data['taxpayer_id']);
            SoukContract::findOrFail($this->editingId)->update($data);
            $this->notify('Contrat modifié.');
        } else {
            SoukContract::create($data + ['is_active' => true, 'municipality_id' => Municipality::value('id')]);
            $this->notify("Contrat d'affermage n° {$data['number']} ajouté.");
        }
        $this->showForm = false;
    }

    /** Flask sou_supprimer : archivage (actif = 0). Refusé ici si une redevance a été payée : le contrat se résilie. */
    public function delete(int $id): void
    {
        $this->authorizeWrite('souk.supprimer');
        $c = SoukContract::findOrFail($id);
        if (TaxDeclaration::where('taxable_type', 'souk_contract')->where('taxable_id', $id)->where('status', 'paye')->exists()) {
            $this->notify('Contrat avec des redevances payées : résiliez-le depuis sa fiche au lieu de le supprimer.', 'error');

            return;
        }
        $c->update(['is_active' => false]);
        activity('parametres')->causedBy(auth()->user())->performedOn($c)->log("Archivage du contrat d'affermage {$c->number}");
        $this->notify("Contrat n° {$c->number} archivé.");
    }

    public static function rows(SoukCalculator $calc, string $search = '', string $souk = ''): Collection
    {
        return SoukContract::with('taxpayer')->where('is_active', true)
            ->when($souk, fn ($q) => $q->where('souk_name', $souk))
            ->when($search, function ($q) use ($search) {
                $s = "%{$search}%";
                $q->where(fn ($w) => $w->where('number', 'like', $s)->orWhere('souk_name', 'like', $s)->orWhere('pitch_number', 'like', $s)
                    ->orWhereHas('taxpayer', fn ($t) => $t->where('last_name', 'like', $s)->orWhere('last_name_ar', 'like', $s)
                        ->orWhere('first_name', 'like', $s)->orWhere('company_name', 'like', $s)->orWhere('cin', 'like', $s)));
            })
            ->latest()->get()->map(fn (SoukContract $c) => ['contract' => $c] + $calc->situation($c));
    }

    public function render(SoukCalculator $calc)
    {
        $all = self::rows($calc, $this->search, $this->souk);
        $kpi = [
            'total' => $all->count(),
            'unpaid' => $all->where('nb_non_declares', '>', 0)->count(),
            'pending' => $all->where('nb_non_declares', 0)->where('nb_emises', '>', 0)->count(),
            'up_to_date' => $all->where('nb_mois_impaye', 0)->count(),
            'amount_due' => $all->sum('total_impaye'),
            'amount_pending' => $all->sum('total_en_attente'),
        ];
        $rows = $all->filter(fn ($r) => match ($this->situation) {
            'impaye' => $r['nb_non_declares'] > 0, 'en_attente' => $r['nb_emises'] > 0, 'ajour' => $r['nb_mois_impaye'] === 0, default => true,
        });
        $s = $this->taxpayerSearch;
        $taxpayers = $this->showForm ? Taxpayer::where('is_active', true)
            ->when($s, fn ($q) => $q->where(fn ($w) => $w->where('last_name', 'like', "%{$s}%")->orWhere('first_name', 'like', "%{$s}%")
                ->orWhere('company_name', 'like', "%{$s}%")->orWhere('cin', 'like', "%{$s}%")))
            ->orWhere('id', $this->form['taxpayer_id'] ?? 0)->orderBy('last_name')->limit(50)->get() : collect();

        return view('livewire.souk.contrats', [
            'rows' => $rows, 'kpi' => $kpi, 'taxpayers' => $taxpayers,
            'souks' => SoukContract::where('is_active', true)->whereNotNull('souk_name')->distinct()->orderBy('souk_name')->pluck('souk_name'),
        ]);
    }
}
