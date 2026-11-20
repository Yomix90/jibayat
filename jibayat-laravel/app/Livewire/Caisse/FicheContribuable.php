<?php

namespace App\Livewire\Caisse;

use App\Domain\Contribuables\Services\TaxpayerService;
use App\Livewire\AppPage;
use App\Livewire\Caisse\Concerns\TaxpayerForm;
use App\Models\Taxpayer;
use App\Models\TaxpayerDocument;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;

/** Fiche contribuable — Flask /contribuables/<id>/detail : taxes, impayés par taxe, déclarations, bulletins, documents, modification. */
#[Title('Fiche contribuable')]
class FicheContribuable extends AppPage
{
    use TaxpayerForm, WithFileUploads;

    public const ROUTE = 'caisse.fiche-contribuable';

    public const DOCUMENT_TYPES = ['cin' => 'CIN', 'contrat' => 'Contrat', 'attestation' => 'Attestation', 'rc' => 'Registre de commerce', 'courrier' => 'Courrier', 'autre' => 'Autre'];

    #[Url(as: 'ctb')]
    public ?int $taxpayerId = null;

    #[Url(as: 'q')]
    public string $search = '';

    public bool $showEdit = false;

    public bool $showDocument = false;

    public $upload = null;

    public string $documentType = 'autre';

    protected function taxpayer(): Taxpayer
    {
        return Taxpayer::with('municipality')->findOrFail($this->taxpayerId);
    }

    public function select(int $id): void
    {
        $this->taxpayerId = $id;
        $this->search = '';
    }

    public function edit(): void
    {
        $this->authorizeWrite('contribuables.modifier');
        $this->fillForm($this->taxpayer());
        $this->showEdit = true;
    }

    public function update(TaxpayerService $service): void
    {
        $this->authorizeWrite('contribuables.modifier');
        if (! $data = $this->validatedForm($this->taxpayerId)) {
            return;
        }
        $service->update($this->taxpayer(), $data, auth()->user());
        $this->showEdit = false;
        $this->notify('Contribuable modifié.');
    }

    public function addDocument(): void
    {
        $this->authorizeWrite('contribuables.modifier');
        $this->reset('upload');
        $this->documentType = 'autre';
        $this->resetValidation();
        $this->showDocument = true;
    }

    public function saveDocument(): void
    {
        $this->authorizeWrite('contribuables.modifier');
        $this->validate(['upload' => ['required', 'file', 'max:10240', 'mimes:pdf,png,jpg,jpeg,doc,docx,xls,xlsx'],
            'documentType' => ['required', Rule::in(array_keys(self::DOCUMENT_TYPES))]], [], ['upload' => 'fichier']);
        $path = $this->upload->store("contribuables/{$this->taxpayerId}", 'local');
        $doc = TaxpayerDocument::create(['taxpayer_id' => $this->taxpayerId, 'type' => $this->documentType, 'file_name' => $this->upload->getClientOriginalName(),
            'path' => $path, 'size' => $this->upload->getSize(), 'uploaded_by' => auth()->id()]);
        activity('contribuables')->causedBy(auth()->user())->performedOn($this->taxpayer())->withProperties(['document' => $doc->file_name])->log('Document ajouté au dossier contribuable');
        $this->reset('upload');
        $this->showDocument = false;
        $this->notify('Document ajouté.');
    }

    public function deleteDocument(int $id): void
    {
        $this->authorizeWrite('contribuables.supprimer');
        $doc = TaxpayerDocument::where('taxpayer_id', $this->taxpayerId)->findOrFail($id);
        if ($doc->path && Storage::disk('local')->exists($doc->path)) {
            Storage::disk('local')->delete($doc->path);
        }
        activity('contribuables')->causedBy(auth()->user())->performedOn($this->taxpayer())->withProperties(['document' => $doc->file_name])->log('Document retiré du dossier contribuable');
        $doc->delete();
        $this->notify('Document supprimé.', 'warning');
    }

    public function render(TaxpayerService $service)
    {
        $s = trim($this->search);
        $results = $s === '' ? collect() : Taxpayer::where('is_active', true)->where(fn ($w) => $w->where('last_name', 'like', "%$s%")
            ->orWhere('first_name', 'like', "%$s%")->orWhere('company_name', 'like', "%$s%")->orWhere('cin', 'like', "%$s%")
            ->orWhere('number', 'like', "%$s%")->orWhere('last_name_ar', 'like', "%$s%"))->orderBy('last_name')->limit(12)->get();
        $t = $this->taxpayerId ? Taxpayer::find($this->taxpayerId) : null;

        return view('livewire.caisse.fiche-contribuable', ['t' => $t, 'results' => $results]
            + ($t ? $service->detail($t) + ['documents' => TaxpayerDocument::where('taxpayer_id', $t->id)->latest()->get()] : []));
    }
}
