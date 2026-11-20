<?php

namespace App\Livewire\Tnb;

use App\Domain\Caisse\Models\TaxDeclaration;
use App\Domain\Tnb\Models\Parcel;
use App\Domain\Tnb\Models\ParcelCoOwner;
use App\Domain\Tnb\Models\ParcelDocument;
use App\Domain\Tnb\Models\TnbFile;
use App\Domain\Tnb\Services\ParcelService;
use App\Domain\Tnb\Services\TnbCalculator;
use App\Livewire\AppPage;
use App\Livewire\Tnb\Concerns\EditsParcels;
use App\Models\Taxpayer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;

/**
 * Fiche terrain — Flask /tnb/<id> : identité, situation, permis (exonération), copropriétaires, transferts,
 * documents, déclarations (annulation motivée), autres terrains du dossier.
 */
#[Title('Fiche terrain')]
class Fiche extends AppPage
{
    use EditsParcels, WithFileUploads;

    public const ROUTE = 'tnb.fiche';

    public const DOCUMENT_TYPES = ['titre_foncier' => 'Titre foncier', 'acte' => 'Acte / contrat', 'plan' => 'Plan', 'permis' => 'Permis', 'cin' => 'CIN', 'autre' => 'Autre'];

    #[Url(as: 'terrain')]
    public ?int $parcelId = null;

    #[Url(as: 'q')]
    public string $search = '';

    public ?string $dialog = null;   // permit | coowner | transfer | document | cancel

    public array $permit = [];

    public array $coOwner = [];

    public ?int $coOwnerId = null;

    public array $transfer = [];

    public $upload = null;

    public string $documentType = 'autre';

    public ?int $cancelId = null;

    public string $cancelReason = '';

    protected function parcel(): ?Parcel
    {
        return $this->parcelId ? Parcel::with('taxpayer')->find($this->parcelId) : null;
    }

    protected function open(string $dialog): void
    {
        $this->resetValidation();
        $this->dialog = $dialog;
    }

    // ── Permis ──────────────────────────────────────────────
    public function addPermit(): void
    {
        $this->authorizeWrite('tnb.modifier');
        $this->permit = ['type' => 'construction', 'number' => '', 'authorized_on' => '', 'description' => ''];
        $this->open('permit');
    }

    public function savePermit(ParcelService $service): void
    {
        $this->authorizeWrite('tnb.modifier');
        $data = $this->validate([
            'permit.type' => ['required', Rule::in(['construction', 'habiter', 'demolition', 'autre'])],
            'permit.number' => ['nullable', 'string', 'max:60'],
            'permit.authorized_on' => ['nullable', 'date', 'required_if:permit.type,construction'],
            'permit.description' => ['nullable', 'string', 'max:500'],
        ], [], ['permit.authorized_on' => "date d'autorisation"])['permit'];
        $p = $service->addPermit($this->parcel(), $data);
        $this->dialog = null;
        $this->notify(match ($p->type) {
            'construction' => "Permis enregistré — exonération TNB {$p->exemption_start_year}–{$p->exemption_end_year} (3 ans). Terrain en construction.",
            'habiter' => "Permis d'habiter enregistré — terrain passé en bâti.",
            default => 'Permis enregistré.',
        });
    }

    // ── Copropriétaires ─────────────────────────────────────
    public function editCoOwner(?int $id = null): void
    {
        $this->authorizeWrite('tnb.modifier');
        $c = $id ? ParcelCoOwner::where('parcel_id', $this->parcelId)->findOrFail($id) : null;
        $this->coOwnerId = $c?->id;
        $this->taxpayerSearch = '';
        $this->coOwner = ['taxpayer_id' => (string) ($c?->taxpayer_id ?? ''), 'share_percent' => $c ? (string) (float) $c->share_percent : '',
            'title_type' => $c?->title_type ?? 'indivision', 'joined_on' => $c?->joined_on?->toDateString() ?? '',
            'notarial_deed' => $c?->notarial_deed ?? '', 'notes' => $c?->notes ?? ''];
        $this->open('coowner');
    }

    public function saveCoOwner(): void
    {
        $this->authorizeWrite('tnb.modifier');
        $parcel = $this->parcel();
        $data = $this->validate([
            'coOwner.taxpayer_id' => ['required', 'exists:taxpayers,id', Rule::notIn([$parcel->taxpayer_id]),
                Rule::unique('parcel_co_owners', 'taxpayer_id')->where('parcel_id', $parcel->id)->ignore($this->coOwnerId)],
            'coOwner.share_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'coOwner.title_type' => ['required', Rule::in(['indivision', 'heritage', 'achat', 'donation', 'autre'])],
            'coOwner.joined_on' => ['nullable', 'date'],
            'coOwner.notarial_deed' => ['nullable', 'string', 'max:255'],
            'coOwner.notes' => ['nullable', 'string', 'max:500'],
        ], ['coOwner.taxpayer_id.not_in' => 'Ce contribuable est déjà le propriétaire principal du terrain.',
            'coOwner.taxpayer_id.unique' => 'Ce contribuable est déjà copropriétaire.'], ['coOwner.taxpayer_id' => 'contribuable'])['coOwner'];
        $data = array_map(fn ($v) => $v === '' ? null : $v, $data);
        $data['share_percent'] ??= 0;
        ParcelCoOwner::updateOrCreate(['id' => $this->coOwnerId], $data + ['parcel_id' => $parcel->id]);
        $this->dialog = null;
        $this->notify($this->coOwnerId ? 'Part modifiée.' : 'Copropriétaire ajouté.');
    }

    public function removeCoOwner(int $id): void
    {
        $this->authorizeWrite('tnb.modifier');
        ParcelCoOwner::where('parcel_id', $this->parcelId)->whereKey($id)->delete();
        $this->notify('Copropriétaire retiré.');
    }

    // ── Transfert ───────────────────────────────────────────
    public function startTransfer(): void
    {
        $this->authorizeWrite('tnb.modifier');
        $this->taxpayerSearch = '';
        $this->transfer = ['new_taxpayer_id' => '', 'transferred_on' => today()->toDateString(), 'reason' => '', 'notarial_deed' => ''];
        $this->open('transfer');
    }

    public function saveTransfer(ParcelService $service): void
    {
        $this->authorizeWrite('tnb.modifier');
        $data = $this->validate([
            'transfer.new_taxpayer_id' => ['required', 'exists:taxpayers,id'],
            'transfer.transferred_on' => ['required', 'date'],
            'transfer.reason' => ['nullable', 'string', 'max:255'],
            'transfer.notarial_deed' => ['nullable', 'string', 'max:255'],
        ], [], ['transfer.new_taxpayer_id' => 'nouveau propriétaire'])['transfer'];
        try {
            $service->transfer($this->parcel(), (int) $data['new_taxpayer_id'], auth()->user(), $data);
        } catch (ValidationException $e) {
            $this->addError('transfer.new_taxpayer_id', $e->validator->errors()->first());

            return;
        }
        $this->dialog = null;
        $this->notify('Transfert effectué — terrain ajouté au dossier n° '.TnbFile::where('taxpayer_id', $data['new_taxpayer_id'])->value('number').'.');
    }

    // ── Documents ───────────────────────────────────────────
    public function addDocument(): void
    {
        $this->authorizeWrite('tnb.modifier');
        $this->reset('upload');
        $this->documentType = 'autre';
        $this->open('document');
    }

    public function saveDocument(): void
    {
        $this->authorizeWrite('tnb.modifier');
        $this->validate([
            'upload' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
            'documentType' => ['required', Rule::in(array_keys(self::DOCUMENT_TYPES))],
        ], [], ['upload' => 'fichier']);
        $path = $this->upload->store("tnb/{$this->parcelId}", 'local');
        ParcelDocument::create(['parcel_id' => $this->parcelId, 'file_name' => $this->upload->getClientOriginalName(), 'path' => $path,
            'type' => $this->documentType, 'size' => $this->upload->getSize(), 'uploaded_by' => auth()->id()]);
        $this->reset('upload');
        $this->dialog = null;
        $this->notify('Document téléversé.');
    }

    public function deleteDocument(int $id): void
    {
        $this->authorizeWrite('tnb.modifier');
        $doc = ParcelDocument::where('parcel_id', $this->parcelId)->findOrFail($id);
        if ($doc->path && Storage::disk('local')->exists($doc->path)) {
            Storage::disk('local')->delete($doc->path);
        }
        $doc->delete();
        $this->notify('Document supprimé.');
    }

    // ── Déclarations ────────────────────────────────────────
    public function askCancel(int $id): void
    {
        $this->authorizeWrite('tnb.supprimer');
        $this->cancelId = $id;
        $this->cancelReason = '';
        $this->open('cancel');
    }

    public function confirmCancel(ParcelService $service): void
    {
        $this->authorizeWrite('tnb.supprimer');
        $this->validate(['cancelReason' => ['required', 'string', 'min:5', 'max:255']], [], ['cancelReason' => 'motif']);
        $decl = TaxDeclaration::where('module', 'TNB')->where('taxable_type', 'parcel')->where('taxable_id', $this->parcelId)->findOrFail($this->cancelId);
        try {
            $service->cancelDeclaration($decl, auth()->user(), $this->cancelReason);
        } catch (ValidationException $e) {
            $this->addError('cancelReason', $e->validator->errors()->first());

            return;
        }
        $this->dialog = null;
        $this->notify("Déclaration {$decl->number} annulée — l'année {$decl->year} redevient à déclarer.");
    }

    /** Flask tnb_archiver : le terrain quitte la liste (ses pièces restent consultables). */
    public function archive(): void
    {
        $this->authorizeWrite('tnb.supprimer');
        $parcel = $this->parcel();
        $parcel->update(['is_archived' => ! $parcel->is_archived]);
        activity('parametres')->causedBy(auth()->user())->performedOn($parcel)->log(($parcel->is_archived ? 'Archivage' : 'Désarchivage')." du terrain {$parcel->number}");
        $this->notify($parcel->is_archived ? 'Terrain archivé.' : 'Terrain désarchivé.');
    }

    public function render(TnbCalculator $calc)
    {
        $parcel = $this->parcel();
        if (! $parcel) {
            $s = $this->search;

            return view('livewire.tnb.fiche-choose', [
                'parcels' => Parcel::with('taxpayer')
                    ->when($s, fn ($q) => $q->where(fn ($w) => $w->where('number', 'like', "%{$s}%")->orWhere('land_title', 'like', "%{$s}%")
                        ->orWhere('parcel_number', 'like', "%{$s}%")->orWhere('subdivision', 'like', "%{$s}%")
                        ->orWhereHas('taxpayer', fn ($t) => $t->where('last_name', 'like', "%{$s}%")->orWhere('cin', 'like', "%{$s}%"))))
                    ->orderByDesc('is_active')->orderBy('is_archived')->orderBy('id')->limit(100)->get(),
            ]);
        }

        $situation = $calc->situation($parcel);
        $permits = $parcel->permits()->get();
        $today = today();
        $alert = $permits->first(fn ($p) => $p->type === 'construction' && $p->exemption_end_year
            && ($d = $today->diffInDays($today->copy()->setDate($p->exemption_end_year, 12, 31), false)) >= 0 && $d <= 60);

        return view('livewire.tnb.fiche', [
            'parcel' => $parcel,
            'file' => TnbFile::where('taxpayer_id', $parcel->taxpayer_id)->value('number'),
            'situation' => $situation,
            'years' => array_merge(array_column($situation['not_declared'], 'annee'), array_column($situation['pending'], 'annee')),
            'exempt' => $calc->exemptYears($parcel),
            'permits' => $permits,
            'alert' => $alert,
            'coOwners' => $parcel->coOwners()->with('taxpayer')->orderByDesc('share_percent')->get(),
            'transfers' => $parcel->transfers()->with(['fromTaxpayer', 'toTaxpayer'])->get(),
            'documents' => $parcel->documents()->get(),
            'others' => Parcel::where('taxpayer_id', $parcel->taxpayer_id)->whereKeyNot($parcel->id)->where('is_active', true)->where('is_archived', false)->orderBy('id')->get(),
            'history' => DB::table('tax_declarations as d')
                ->leftJoin('payment_slips as s', fn ($j) => $j->on('s.tax_declaration_id', '=', 'd.id')->whereNull('s.cancelled_at'))
                ->where('d.module', 'TNB')->where('d.taxable_type', 'parcel')->where('d.taxable_id', $parcel->id)
                ->orderByDesc('d.year')->orderByDesc('d.id')
                ->get(['d.id', 'd.number', 'd.year', 'd.total', 'd.status', 'd.declared_on', 'd.cancelled_at', 'd.cancellation_reason',
                    's.slip_number', 's.status as slip_status', 's.receipt_number', 's.rejection_reason']),
            'taxpayers' => in_array($this->dialog, ['coowner', 'transfer']) || $this->showForm ? $this->taxpayerOptions() : collect(),
            'statuses' => self::parcelStatuses(),
        ]);
    }

    protected function taxpayerOptions(): \Illuminate\Support\Collection
    {
        $s = $this->taxpayerSearch;
        $keep = $this->dialog === 'transfer' ? ($this->transfer['new_taxpayer_id'] ?? 0) : ($this->coOwner['taxpayer_id'] ?? 0);

        return Taxpayer::where('is_active', true)
            ->when($s, fn ($q) => $q->where(fn ($w) => $w->where('last_name', 'like', "%{$s}%")->orWhere('first_name', 'like', "%{$s}%")
                ->orWhere('last_name_ar', 'like', "%{$s}%")->orWhere('cin', 'like', "%{$s}%")->orWhere('company_name', 'like', "%{$s}%")))
            ->orWhere('id', $keep ?: 0)->orderBy('last_name')->limit(50)->get();
    }
}
