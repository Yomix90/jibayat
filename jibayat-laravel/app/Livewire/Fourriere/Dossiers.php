<?php

namespace App\Livewire\Fourriere;

use App\Domain\Fourriere\Models\ImpoundCase;
use App\Domain\Fourriere\Models\ImpoundSetting;
use App\Domain\Fourriere\Services\ImpoundCalculator;
use App\Livewire\AppPage;
use App\Models\Municipality;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

/**
 * Dossiers de fourrière — Flask /fourriere : liste, indicateurs, mise en fourrière, modification.
 * Le tarif journalier est celui du type de véhicule dans l'arrêté fiscal, figé à l'entrée.
 */
#[Title('Dossiers — Fourrière')]
class Dossiers extends AppPage
{
    public const ROUTE = 'fourriere.dossiers';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'statut')]
    public string $status = '';

    #[Url(as: 'type')]
    public string $type = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = [];

    protected function emptyForm(): array
    {
        return ['number' => '', 'deposit_number' => '', 'vehicle_type' => '', 'registration' => '', 'impounded_on' => today()->toDateString(),
            'depositor' => '', 'reason' => '', 'towing_fee' => (string) app(ImpoundCalculator::class)->defaultTowingFee(), 'notes' => ''];
    }

    public static function nextNumber(): string
    {
        $max = (int) ImpoundCase::whereRaw("number REGEXP '^[0-9]+$'")->max(DB::raw('CAST(number AS UNSIGNED)'));

        return (string) ($max > 0 ? $max + 1 : ImpoundCase::count() + 1);
    }

    public function create(): void
    {
        $this->authorizeWrite('fourriere.ajouter');
        $this->editingId = null;
        $this->form = ['number' => self::nextNumber()] + $this->emptyForm();
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorizeWrite('fourriere.modifier');
        $c = ImpoundCase::findOrFail($id);
        $this->editingId = $c->id;
        $this->form = collect($this->emptyForm())->mapWithKeys(fn ($v, $k) => [$k => match ($k) {
            'impounded_on' => $c->impounded_on?->toDateString() ?? '',
            'towing_fee' => (string) (float) $c->towing_fee,
            default => (string) ($c->{$k} ?? ''),
        }])->all();
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(ImpoundCalculator $calc): void
    {
        $this->authorizeWrite($this->editingId ? 'fourriere.modifier' : 'fourriere.ajouter');
        $data = $this->validate([
            'form.number' => ['required', 'string', 'max:40', Rule::unique('impound_cases', 'number')->ignore($this->editingId)],
            'form.deposit_number' => ['nullable', 'string', 'max:40'],
            'form.vehicle_type' => ['required', 'string', 'max:60'],
            'form.registration' => ['required', 'string', 'max:40'],
            'form.impounded_on' => ['required', 'date', 'before_or_equal:today'],
            'form.depositor' => ['nullable', 'string', 'max:60'],
            'form.reason' => ['nullable', 'string', 'max:255'],
            'form.towing_fee' => ['nullable', 'numeric', 'min:0'],
            'form.notes' => ['nullable', 'string', 'max:1000'],
        ], [], ['form.number' => 'n° de dossier', 'form.vehicle_type' => 'type de véhicule', 'form.registration' => 'immatriculation',
            'form.impounded_on' => 'date de mise en fourrière'])['form'];
        $data = array_map(fn ($v) => $v === '' ? null : $v, $data);
        $data['towing_fee'] ??= 0;
        $data['registration'] = mb_strtoupper(trim($data['registration']));
        $data['daily_rate'] = $calc->dailyRate($data['vehicle_type']);

        if ($this->editingId) {
            $c = ImpoundCase::findOrFail($this->editingId);
            if ($c->status === 'sortie') {
                $this->notify('Un véhicule sorti ne se modifie plus.', 'error');

                return;
            }
            $c->update($data);
            $this->notify('Dossier modifié.');
        } else {
            ImpoundCase::create($data + ['status' => 'en_fourriere', 'is_active' => true, 'municipality_id' => Municipality::value('id')]);
            $this->notify("Véhicule {$data['registration']} mis en fourrière (dossier n° {$data['number']}).");
        }
        $this->showForm = false;
    }

    public static function rows(string $search = '', string $status = '', string $type = ''): Collection
    {
        return ImpoundCase::where('is_active', true)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($type, fn ($q) => $q->where('vehicle_type', $type))
            ->when($search, function ($q) use ($search) {
                $s = "%{$search}%";
                $q->where(fn ($w) => $w->where('registration', 'like', $s)->orWhere('number', 'like', $s)->orWhere('deposit_number', 'like', $s)
                    ->orWhere('owner_name', 'like', $s)->orWhere('owner_cin', 'like', $s));
            })
            ->orderByDesc('impounded_on')->get()
            ->map(fn (ImpoundCase $c) => ['case' => $c] + ImpoundCalculator::enrich($c));
    }

    public function render(ImpoundCalculator $calc)
    {
        $rows = self::rows($this->search, $this->status, $this->type);

        return view('livewire.fourriere.dossiers', [
            'rows' => $rows,
            'stats' => ImpoundCalculator::stats(self::rows()),
            'types' => $calc->vehicleTypes(),
            'depositors' => ImpoundSetting::where('category', 'deposant')->where('is_active', true)->orderBy('sort_order')->get(),
            'reasons' => ImpoundSetting::where('category', 'motif')->where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }
}
