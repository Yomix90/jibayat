<?php

namespace App\Livewire\Regie;

use App\Domain\Regie\Models\ArchiveBox;
use App\Livewire\AppPage;
use Livewire\Attributes\Title;

/** Boîtes d'archive : création, emplacement, fermeture, contenu, étiquette imprimable. */
#[Title('Boîtes d’archive')]
class Boites extends AppPage
{
    public const ROUTE = 'regie.boites';

    public bool $showForm = false;

    public ?int $editId = null;

    public array $form = [];

    public function openForm(?int $id = null): void
    {
        $this->authorizeWrite('archives.modifier');
        $b = $id ? ArchiveBox::findOrFail($id) : null;
        $this->editId = $id;
        $next = ArchiveBox::where('year', now()->year)->count() + 1;
        $this->form = ['code' => $b?->code ?? sprintf('B%d-%02d', now()->year, $next), 'label' => $b?->label ?? '', 'year' => (string) ($b?->year ?? now()->year),
            'location' => $b?->location ?? '', 'description' => $b?->description ?? ''];
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorizeWrite('archives.modifier');
        $data = $this->validate(['form.code' => ['required', 'string', 'max:40', 'unique:archive_boxes,code'.($this->editId ? ','.$this->editId : '')],
            'form.label' => ['required', 'string', 'max:150'], 'form.year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'form.location' => ['nullable', 'string', 'max:150'], 'form.description' => ['nullable', 'string', 'max:500']], [], ['form.code' => 'code', 'form.label' => 'libellé'])['form'];
        $data['year'] = $data['year'] ?: null;
        $box = $this->editId ? tap(ArchiveBox::findOrFail($this->editId))->update($data) : ArchiveBox::create($data);
        activity('regie')->causedBy(auth()->user())->performedOn($box)->log($this->editId ? 'Boîte d’archive modifiée' : 'Boîte d’archive créée');
        $this->showForm = false;
        $this->notify("Boîte {$box->code} enregistrée.");
    }

    public function toggle(int $id): void
    {
        $this->authorizeWrite('archives.modifier');
        $b = ArchiveBox::findOrFail($id);
        $b->update(['is_closed' => ! $b->is_closed]);
        $this->notify($b->is_closed ? 'Boîte fermée.' : 'Boîte rouverte.');
    }

    public function render()
    {
        return view('livewire.regie.boites', ['boxes' => ArchiveBox::withCount('documents')->orderByDesc('year')->orderBy('code')->get()]);
    }
}
