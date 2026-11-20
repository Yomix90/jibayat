<?php

namespace App\Livewire\Regie;

use App\Domain\Regie\Models\ArchiveBox;
use App\Domain\Regie\Models\PrintedDocument;
use App\Domain\Regie\Services\DocumentRegistry;
use App\Livewire\AppPage;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Registre des documents imprimés : chaque pièce PDF reçoit un n° et un QR de contrôle à l'impression.
 * Filtres, scan joint, rangement en boîte d'archive.
 */
#[Title('Registre des documents')]
class Documents extends AppPage
{
    use WithFileUploads;
    use WithPagination;

    public const ROUTE = 'regie.documents';

    #[Url(as: 'n')]
    public string $search = '';

    #[Url(as: 'famille')]
    public string $family = '';

    #[Url(as: 'etat')]
    public string $state = '';

    #[Url(as: 'boite')]
    public string $box = '';

    public array $selected = [];

    public string $targetBox = '';

    public ?int $scanId = null;

    public $scan;

    public function updated(string $name): void
    {
        if (in_array($name, ['search', 'family', 'state', 'box'], true)) {
            $this->resetPage();
        }
    }

    public function fileSelected(DocumentRegistry $registry): void
    {
        $this->authorizeWrite('archives.modifier');
        $this->validate(['selected' => ['required', 'array', 'min:1'], 'targetBox' => ['required']], ['selected.required' => 'Sélectionnez des pièces.'], ['targetBox' => 'boîte']);
        try {
            $n = $registry->file(array_map('intval', $this->selected), $this->targetBox === 'none' ? null : (int) $this->targetBox, auth()->user());
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->addError('targetBox', collect($e->errors())->flatten()->first());

            return;
        }
        $this->selected = [];
        $this->notify("$n pièce(s) rangée(s).");
    }

    public function askScan(int $id): void
    {
        $this->authorizeWrite('archives.ajouter');
        $this->scanId = $id;
        $this->scan = null;
        $this->resetValidation();
    }

    public function saveScan(DocumentRegistry $registry): void
    {
        $this->authorizeWrite('archives.ajouter');
        $this->validate(['scan' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240']], [], ['scan' => 'scan']);
        $registry->attachScan(PrintedDocument::findOrFail($this->scanId), $this->scan, auth()->user());
        $this->scanId = null;
        $this->notify('Scan joint au registre.');
    }

    public function render()
    {
        $q = PrintedDocument::with(['printer', 'box'])
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w->where('number', 'like', "%{$this->search}%")->orWhere('title', 'like', "%{$this->search}%")))
            ->when($this->family, fn ($q) => $q->where('type', 'like', $this->family.'.%'))
            ->when($this->state === 'unscanned', fn ($q) => $q->whereNull('scan_path'))
            ->when($this->state === 'scanned', fn ($q) => $q->whereNotNull('scan_path'))
            ->when($this->state === 'unfiled', fn ($q) => $q->whereNull('archive_box_id'))
            ->when($this->box, fn ($q) => $q->where('archive_box_id', $this->box))
            ->orderByDesc('last_printed_at');

        return view('livewire.regie.documents', ['rows' => $q->paginate(30), 'families' => DocumentRegistry::TYPES,
            'boxes' => ArchiveBox::orderByDesc('year')->orderBy('code')->get()]);
    }
}
