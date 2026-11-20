<?php

namespace App\Livewire\Regie;

use App\Domain\Regie\Models\CashEntry;
use App\Domain\Regie\Services\EntryService;
use App\Livewire\AppPage;
use App\Models\Rubric;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/** Journal des recettes saisies à la régie : filtres, total, annulation motivée d'une recette validée. */
#[Title('Journal des recettes')]
class Recettes extends AppPage
{
    use WithPagination;

    public const ROUTE = 'regie.recettes';

    /** valide | brouillon | annule | '' */
    #[Url(as: 'etat')]
    public string $status = 'valide';

    #[Url(as: 'du')]
    public string $from = '';

    #[Url(as: 'au')]
    public string $to = '';

    #[Url(as: 'rubrique')]
    public string $rubric = '';

    #[Url(as: 'mode')]
    public string $mode = '';

    #[Url(as: 'n')]
    public string $search = '';

    public array $selected = [];

    public ?int $cancelId = null;

    public string $cancelReason = '';

    public ?int $editId = null;

    public array $edit = [];

    public bool $showEdit = false;

    public function mount(): void
    {
        $this->from = $this->from ?: now()->startOfMonth()->toDateString();
        $this->to = $this->to ?: today()->toDateString();
    }

    public function updated(string $name): void
    {
        if (in_array($name, ['status', 'from', 'to', 'rubric', 'mode', 'search'], true)) {
            $this->resetPage();
            $this->selected = [];
        }
    }

    protected function query()
    {
        return CashEntry::query()->with(['rubric', 'author', 'validator'])
            ->when($this->status === 'annule', fn ($q) => $q->whereNotNull('cancelled_at'))
            ->when(in_array($this->status, ['valide', 'brouillon'], true), fn ($q) => $q->whereNull('cancelled_at')->where('status', $this->status))
            ->when($this->from, fn ($q) => $q->where('entry_date', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->where('entry_date', '<=', $this->to))
            ->when($this->rubric, fn ($q) => $q->where('rubric_id', $this->rubric))
            ->when($this->mode, fn ($q) => $q->where('payment_mode', $this->mode))
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w->where('number', 'like', "%{$this->search}%")
                ->orWhere('reference', 'like', "%{$this->search}%")->orWhere('payer', 'like', "%{$this->search}%")->orWhere('description', 'like', "%{$this->search}%")))
            ->orderByDesc('entry_date')->orderByDesc('id');
    }

    public function validateSelected(EntryService $service): void
    {
        $this->authorizeWrite('paiements.valider');
        $n = $service->validate(array_map('intval', $this->selected), auth()->user());
        $this->selected = [];
        $this->notify("$n recette(s) validée(s).");
    }

    public function validateOne(int $id, EntryService $service): void
    {
        $this->authorizeWrite('paiements.valider');
        $service->validate([$id], auth()->user());
        $this->selected = array_values(array_diff($this->selected, [$id, (string) $id]));
        $this->notify('Recette validée.');
    }

    public function validateAll(EntryService $service): void
    {
        $this->authorizeWrite('paiements.valider');
        $n = $service->validate($this->query()->pluck('id')->all(), auth()->user());
        $this->notify("$n recette(s) validée(s).");
    }

    public function openEdit(int $id): void
    {
        $this->authorizeWrite('recettes.ajouter');
        $e = CashEntry::findOrFail($id);
        $this->editId = $id;
        $this->edit = ['rubric_id' => $e->rubric_id, 'payment_mode' => $e->payment_mode, 'quantity' => $e->quantity === null ? '' : (string) (float) $e->quantity,
            'unit_price' => $e->unit_price === null ? '' : (string) (float) $e->unit_price, 'amount' => (string) (float) $e->amount,
            'reference' => (string) $e->reference, 'payer' => (string) $e->payer, 'description' => (string) $e->description];
        $this->resetValidation();
        $this->showEdit = true;
    }

    public function saveEdit(EntryService $service): void
    {
        $this->authorizeWrite('recettes.ajouter');
        try {
            $service->updateDraft(CashEntry::findOrFail($this->editId), $this->edit, auth()->user());
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw \Illuminate\Validation\ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn ($m, $k) => [preg_replace('/^lines\.0\./', 'edit.', $k) => $m])->all());
        }
        $this->showEdit = false;
        $this->notify('Brouillon modifié.');
    }

    public function askCancel(int $id): void
    {
        $e = CashEntry::findOrFail($id);
        $this->authorizeWrite($e->status === 'brouillon' ? 'recettes.ajouter' : 'paiements.valider');
        $this->cancelId = $id;
        $this->cancelReason = '';
        $this->resetValidation();
    }

    public function confirmCancel(EntryService $service): void
    {
        $e = CashEntry::findOrFail($this->cancelId);
        $this->authorizeWrite($e->status === 'brouillon' ? 'recettes.ajouter' : 'paiements.valider');
        $this->validate(['cancelReason' => ['required', 'string', 'min:5', 'max:255']], [], ['cancelReason' => 'motif']);
        if (! $e->isCancelled()) {
            try {
                $service->cancel($e, auth()->user(), $this->cancelReason);
            } catch (\Illuminate\Validation\ValidationException $ex) {
                throw \Illuminate\Validation\ValidationException::withMessages(['cancelReason' => collect($ex->errors())->flatten()->first()]);
            }
        }
        $this->cancelId = null;
        $this->notify('Recette annulée ; elle reste visible avec son motif.', 'warning');
    }

    public function render()
    {
        $q = $this->query();

        return view('livewire.regie.recettes', [
            'rows' => (clone $q)->paginate(30), 'total' => (float) (clone $q)->sum('amount'), 'count' => (clone $q)->count(),
            'rubrics' => Rubric::orderBy('code')->get(['id', 'code', 'label']),
            'drafts' => $this->status === 'brouillon',
        ]);
    }
}
