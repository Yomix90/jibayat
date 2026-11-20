<?php

namespace App\Livewire\Declarations;

use App\Domain\Boissons\Models\BeverageOutlet;
use App\Domain\Declarations\Services\DeclarationService;
use App\Domain\Tnb\Models\Parcel;
use App\Domain\Transport\Models\Vehicle;
use App\Livewire\AppPage;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

/** Dépôt d'une déclaration d'assiette : taxe ➜ type (article, délai) ➜ objet ➜ informations ➜ enregistrement et impression. */
#[Title('Nouvelle déclaration')]
class Nouvelle extends AppPage
{
    public const ROUTE = 'declarations.nouvelle';

    #[Url(as: 'taxe')]
    public string $module = 'DEBITS_BOISSONS';

    #[Url(as: 'type')]
    public string $type = '';

    #[Url(as: 'objet')]
    public ?int $objectId = null;

    public string $search = '';

    public array $form = [];

    public function mount(): void
    {
        $this->module = isset(DeclarationService::MODULES[$this->module]) ? $this->module : 'DEBITS_BOISSONS';
        if (! isset(DeclarationService::TYPES[$this->module][$this->type])) {
            $this->type = '';
        }
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->form = ['filed_on' => today()->toDateString(), 'event_on' => today()->toDateString(), 'fiscal_year' => (string) (in_array($this->type, ['recettes_annuelles', 'inactivite'], true) ? now()->year - 1 : now()->year),
            'resume_on' => '', 'reason' => '', 'reason_choice' => '', 'amount' => '', 'new_holder' => '', 'new_address' => '', 'inactivity' => 'totale', 'inactive_part' => '', 'notes' => '', 'signed' => false];
        $this->resetValidation();
    }

    public function chooseModule(string $module): void
    {
        abort_unless(isset(DeclarationService::MODULES[$module]), 404);
        $this->module = $module;
        $this->type = '';
        $this->objectId = null;
        $this->resetForm();
    }

    public function chooseType(string $type): void
    {
        DeclarationService::type($this->module, $type);
        $this->type = $type;
        $this->resetForm();
    }

    public function chooseObject(int $id): void
    {
        $this->objectId = $id;
        $this->search = '';
    }

    protected function objects()
    {
        $s = trim($this->search);

        return match ($this->module) {
            'TNB' => Parcel::with('taxpayer')->where('is_active', true)->where('is_census', false)
                ->when($s, fn ($q) => $q->where(fn ($w) => $w->where('number', 'like', "%$s%")->orWhere('land_title', 'like', "%$s%")->orWhere('parcel_number', 'like', "%$s%")
                    ->orWhereHas('taxpayer', fn ($t) => $t->where('last_name', 'like', "%$s%")->orWhere('cin', 'like', "%$s%"))))->orderBy('number')->limit(15)->get(),
            'DEBITS_BOISSONS' => BeverageOutlet::with('taxpayer')->where('is_active', true)
                ->when($s, fn ($q) => $q->where(fn ($w) => $w->where('number', 'like', "%$s%")->orWhere('name', 'like', "%$s%")
                    ->orWhereHas('taxpayer', fn ($t) => $t->where('last_name', 'like', "%$s%")->orWhere('cin', 'like', "%$s%"))))->orderBy('name')->limit(15)->get(),
            'STATIONNEMENT' => Vehicle::with('taxpayer')->where('is_active', true)
                ->when($s, fn ($q) => $q->where(fn ($w) => $w->where('number', 'like', "%$s%")->orWhere('registration', 'like', "%$s%")
                    ->orWhereHas('taxpayer', fn ($t) => $t->where('last_name', 'like', "%$s%")->orWhere('cin', 'like', "%$s%"))))->orderBy('registration')->limit(15)->get(),
        };
    }

    public function save(DeclarationService $service)
    {
        $perm = DeclarationService::MODULES[$this->module]['perm'].'.modifier';
        $this->authorizeWrite($perm);
        $t = DeclarationService::type($this->module, $this->type);
        $f = $t['fields'];
        $rules = ['form.filed_on' => ['required', 'date', 'before_or_equal:today'], 'form.signed' => ['boolean'], 'form.notes' => ['nullable', 'string', 'max:500'],
            'objectId' => ['required', 'integer']];
        $rules += in_array('event_on', $f, true) ? ['form.event_on' => ['required', 'date']] : [];
        $rules += in_array('fiscal_year', $f, true) ? ['form.fiscal_year' => ['required', 'integer', 'between:2000,'.(now()->year + 1)]] : [];
        $rules += in_array('amount', $f, true) ? ['form.amount' => ['required', 'numeric', 'min:0']] : [];
        $rules += in_array('resume_on', $f, true) ? ['form.resume_on' => ['nullable', 'date', 'after:form.event_on']] : [];
        $rules += in_array('reason', $f, true) ? ['form.reason' => [$this->type === 'cessation' ? 'required' : 'nullable', 'string', 'max:255']] : [];
        $rules += in_array('new_holder', $f, true) ? ['form.new_holder' => ['nullable', 'string', 'max:255']] : [];
        $rules += in_array('new_address', $f, true) ? ['form.new_address' => ['nullable', 'string', 'max:255']] : [];
        $rules += in_array('inactivity', $f, true) ? ['form.inactivity' => ['required', 'in:totale,partielle'], 'form.inactive_part' => ['required_if:form.inactivity,partielle', 'nullable', 'string', 'max:255']] : [];
        $this->validate($rules, ['objectId.required' => 'Choisissez le terrain, l’établissement ou le véhicule concerné.'],
            ['form.event_on' => 'date du fait', 'form.filed_on' => 'date de dépôt', 'form.reason' => 'motif', 'form.amount' => $t['amount_label'] ?? 'montant',
                'form.resume_on' => 'date de reprise prévue', 'form.fiscal_year' => 'année']);
        try {
            $d = $service->file($this->module, $this->type, $this->objectId, array_map(fn ($v) => $v === '' ? null : $v, $this->form), auth()->user());
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return null;
        }
        session()->flash('toast', __('Déclaration :n enregistrée.', ['n' => $d->number]).($d->is_late ? ' '.__('Déposée hors délai.') : ''));
        session()->flash('toast_type', $d->is_late ? 'warning' : 'success');

        return $this->redirectRoute('declarations.registre', ['n' => $d->number], navigate: false);
    }

    public function render(DeclarationService $service)
    {
        $t = $this->type ? DeclarationService::TYPES[$this->module][$this->type] : null;
        $object = $this->objectId ? DeclarationService::MODULES[$this->module]['model']::with('taxpayer')->find($this->objectId) : null;
        $deadline = $t ? DeclarationService::deadline($t, $this->form['event_on'] ?? null, isset($this->form['fiscal_year']) && is_numeric($this->form['fiscal_year']) ? (int) $this->form['fiscal_year'] : null) : null;
        $late = $deadline && ! empty($this->form['filed_on']) && CarbonImmutable::parse($this->form['filed_on'])->greaterThan($deadline);

        return view('livewire.declarations.nouvelle', [
            'types' => DeclarationService::TYPES[$this->module], 't' => $t, 'object' => $object, 'results' => $object && $this->search === '' ? collect() : $this->objects(),
            'deadline' => $deadline, 'late' => $late, 'open' => $object && $this->module === 'DEBITS_BOISSONS' ? $service->openCessation($this->module, $object) : null,
            'canWrite' => $this->allowed(DeclarationService::MODULES[$this->module]['perm'].'.modifier'),
        ]);
    }
}
