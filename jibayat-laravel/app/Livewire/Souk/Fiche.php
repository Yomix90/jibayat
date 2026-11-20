<?php

namespace App\Livewire\Souk;

use App\Domain\Souk\Models\SoukContract;
use App\Domain\Souk\Models\SoukContractDocument;
use App\Domain\Souk\Services\ContractService;
use App\Domain\Souk\Services\SoukCalculator;
use App\Livewire\AppPage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;

/**
 * Fiche contrat d'affermage — Flask /souks/<id> : fermier, contrat, périodes de renouvellement, mois impayés,
 * renouvellement, résiliation, documents, historique.
 */
#[Title('Fiche contrat — Souk')]
class Fiche extends AppPage
{
    use WithFileUploads;

    public const ROUTE = 'souk.fiche';

    public const DOCUMENT_TYPES = ['contrat' => 'Contrat', 'cahier_charges' => 'Cahier des charges', 'pv' => "PV d'adjudication", 'cin' => 'CIN', 'autre' => 'Autre'];

    #[Url(as: 'contrat')]
    public ?int $contractId = null;

    #[Url(as: 'q')]
    public string $search = '';

    public ?string $dialog = null;   // terminate | document

    public string $terminateOn = '';

    public string $terminateReason = '';

    public $upload = null;

    public string $documentType = 'contrat';

    protected function contract(): SoukContract
    {
        return SoukContract::findOrFail($this->contractId);
    }

    public array $renewal = [];

    /** Prolongation en fin de période : redevance proposée (taux du contrat), modifiable par l'agent. */
    public function openRenew(ContractService $service): void
    {
        $this->authorizeWrite('souk.modifier');
        $n = $service->nextPeriod($this->contract());
        $this->renewal = ['date_debut' => $n['date_debut'], 'date_fin' => $n['date_fin'], 'actuelle' => $n['redevance_actuelle'],
            'taux' => (string) $n['taux'], 'redevance' => number_format($n['redevance'], 2, '.', ''), 'notes' => ''];
        $this->resetValidation();
        $this->dialog = 'renew';
    }

    public function updatedRenewalTaux(): void
    {
        if (is_numeric($this->renewal['taux'] ?? null)) {
            $this->renewal['redevance'] = number_format(round((float) $this->renewal['actuelle'] * (1 + (float) $this->renewal['taux'] / 100), 2), 2, '.', '');
        }
    }

    public function renew(ContractService $service): void
    {
        $this->authorizeWrite('souk.modifier');
        $this->validate(['renewal.redevance' => ['required', 'numeric', 'min:0'], 'renewal.taux' => ['nullable', 'numeric', 'min:-100', 'max:1000'],
            'renewal.notes' => ['nullable', 'string', 'max:255']], [], ['renewal.redevance' => 'nouvelle redevance', 'renewal.taux' => 'augmentation']);
        $p = $service->renew($this->contract(), auth()->user(), (float) $this->renewal['redevance'], null, $this->renewal['notes'] ?: null);
        $this->dialog = null;
        $this->notify(__('Contrat prolongé').' : '.CarbonImmutable::parse($p['date_debut'])->format('d/m/Y').' → '.CarbonImmutable::parse($p['date_fin'])->format('d/m/Y')
            .' — '.number_format($p['redevance'], 2, ',', ' ').' DH/mois.');
    }

    public function openTerminate(): void
    {
        $this->authorizeWrite('souk.modifier');
        $this->terminateOn = today()->toDateString();
        $this->terminateReason = '';
        $this->resetValidation();
        $this->dialog = 'terminate';
    }

    public function terminate(ContractService $service): void
    {
        $this->authorizeWrite('souk.modifier');
        $this->validate(['terminateOn' => ['required', 'date'], 'terminateReason' => ['nullable', 'string', 'max:255']], [], ['terminateOn' => 'date de résiliation']);
        $service->terminate($this->contract(), auth()->user(), $this->terminateOn, $this->terminateReason ?: null);
        $this->dialog = null;
        $this->notify('Contrat résilié au '.CarbonImmutable::parse($this->terminateOn)->format('d/m/Y').'.', 'warning');
    }

    public function openDocument(): void
    {
        $this->authorizeWrite('souk.modifier');
        $this->reset('upload');
        $this->resetValidation();
        $this->dialog = 'document';
    }

    public function saveDocument(): void
    {
        $this->authorizeWrite('souk.modifier');
        $this->validate([
            'upload' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
            'documentType' => ['required', Rule::in(array_keys(self::DOCUMENT_TYPES))],
        ], [], ['upload' => 'fichier']);
        $path = $this->upload->store("souk/{$this->contractId}", 'local');
        SoukContractDocument::create(['souk_contract_id' => $this->contractId, 'file_name' => $this->upload->getClientOriginalName(),
            'path' => $path, 'type' => $this->documentType, 'uploaded_by' => auth()->id()]);
        $this->reset('upload');
        $this->dialog = null;
        $this->notify('Document téléversé.');
    }

    public function deleteDocument(int $id): void
    {
        $this->authorizeWrite('souk.modifier');
        $doc = SoukContractDocument::where('souk_contract_id', $this->contractId)->findOrFail($id);
        if ($doc->path && Storage::disk('local')->exists($doc->path)) {
            Storage::disk('local')->delete($doc->path);
        }
        $doc->delete();
        $this->notify('Document supprimé.');
    }

    public function render(SoukCalculator $calc)
    {
        $contract = $this->contractId ? SoukContract::with('taxpayer')->find($this->contractId) : null;
        if (! $contract) {
            $s = $this->search;

            return view('livewire.souk.fiche-choose', [
                'contracts' => SoukContract::with('taxpayer')
                    ->when($s, fn ($q) => $q->where(fn ($w) => $w->where('number', 'like', "%{$s}%")->orWhere('souk_name', 'like', "%{$s}%")
                        ->orWhereHas('taxpayer', fn ($t) => $t->where('last_name', 'like', "%{$s}%")->orWhere('cin', 'like', "%{$s}%"))))
                    ->orderByDesc('is_active')->orderBy('souk_name')->limit(100)->get(),
            ]);
        }
        $situation = $calc->situation($contract);

        return view('livewire.souk.fiche', [
            'contract' => $contract, 'situation' => $situation,
            'periods' => $calc->periods($contract),
            'groups' => SoukCalculator::groups($situation['months']),
            'documents' => $contract->documents()->get(),
            'history' => Encaisser::history($contract),
        ]);
    }
}
