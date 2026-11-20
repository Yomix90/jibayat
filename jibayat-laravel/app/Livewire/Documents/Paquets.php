<?php

namespace App\Livewire\Documents;

use App\Domain\Documents\Models\StampBook;
use App\Domain\Documents\Models\StampPack;
use App\Domain\Documents\Models\StampValue;
use App\Domain\Documents\Services\StampService;
use App\Livewire\AppPage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

/** Paquets de timbres — Flask /regie/paquets : réception, modification (avant ouverture), suppression, ouverture en 10 carnets. */
#[Title('Paquets — Régie des timbres')]
class Paquets extends AppPage
{
    public const ROUTE = 'documents.paquets';

    #[Url(as: 'statut')]
    public string $status = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = [];

    public function create(): void
    {
        $this->authorizeWrite('regie.ajouter');
        $this->editingId = null;
        $this->form = ['stamp_value_id' => (string) StampValue::where('is_active', true)->value('id'), 'number' => '', 'first_number' => '', 'last_number' => '', 'received_on' => today()->toDateString()];
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorizeWrite('regie.modifier');
        $p = StampPack::findOrFail($id);
        $this->editingId = $p->id;
        $this->form = ['stamp_value_id' => (string) $p->stamp_value_id, 'number' => $p->number, 'first_number' => (string) $p->first_number,
            'last_number' => (string) $p->last_number, 'received_on' => $p->received_on?->toDateString() ?? ''];
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorizeWrite($this->editingId ? 'regie.modifier' : 'regie.ajouter');
        $data = $this->validate([
            'form.stamp_value_id' => ['required', 'exists:stamp_values,id'],
            'form.number' => ['required', 'string', 'max:40', Rule::unique('stamp_packs', 'number')->ignore($this->editingId)],
            'form.first_number' => ['required', 'integer', 'min:0'],
            'form.last_number' => ['required', 'integer', 'gte:form.first_number'],
            'form.received_on' => ['required', 'date'],
        ], [], ['form.stamp_value_id' => 'valeur', 'form.number' => 'n° de paquet', 'form.first_number' => 'premier n°', 'form.last_number' => 'dernier n°'])['form'];
        $data['quantity'] = StampService::quantity($data['first_number'], $data['last_number']);
        if ($this->editingId) {
            $p = StampPack::findOrFail($this->editingId);
            if ($p->status !== 'recu') {
                $this->notify('Impossible de modifier un paquet déjà ouvert.', 'error');

                return;
            }
            $p->update($data);
            $this->notify('Paquet modifié.');
        } else {
            StampPack::create($data + ['status' => 'recu', 'agent_id' => auth()->id()]);
            $this->notify("Paquet {$data['number']} enregistré ({$data['quantity']} vignettes).");
        }
        $this->showForm = false;
    }

    public function delete(int $id): void
    {
        $this->authorizeWrite('regie.supprimer');
        $p = StampPack::findOrFail($id);
        if ($p->status !== 'recu' || StampBook::where('stamp_pack_id', $id)->exists()) {
            $this->notify('Impossible de supprimer un paquet ouvert ou dont des carnets existent.', 'error');

            return;
        }
        $p->delete();
        $this->notify('Paquet supprimé.');
    }

    public function open(int $id, StampService $service): void
    {
        $this->authorizeWrite('regie.modifier');
        try {
            $n = $service->openPack(StampPack::findOrFail($id));
        } catch (ValidationException $e) {
            $this->notify($e->validator->errors()->first(), 'error');

            return;
        }
        $this->notify("{$n} carnets générés.");
    }

    public function render()
    {
        return view('livewire.documents.paquets', [
            'packs' => StampPack::with('stampValue')->when($this->status, fn ($q) => $q->where('status', $this->status))
                ->withCount(['books', 'books as stock_count' => fn ($q) => $q->where('status', 'en_stock'),
                    'books as assigned_count' => fn ($q) => $q->where('status', 'affecte'), 'books as consumed_count' => fn ($q) => $q->where('status', 'consomme')])
                ->latest()->get(),
            'values' => StampValue::where('is_active', true)->orderBy('label')->get(),
        ]);
    }
}
