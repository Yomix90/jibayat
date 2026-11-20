<?php

namespace App\Livewire\Fourriere;

use App\Domain\Fourriere\Models\AuctionGroup;
use App\Domain\Fourriere\Models\AuctionGroupVehicle;
use App\Domain\Fourriere\Models\ImpoundCase;
use App\Domain\Fourriere\Services\ImpoundCalculator;
use App\Domain\Fourriere\Services\ImpoundService;
use App\Livewire\AppPage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\WithFileUploads;

/**
 * Groupes d'enchères — Flask /fourriere/encheres (groupes) : création, prix d'ouverture, date et lieu, véhicules,
 * impression de la liste, enregistrement des ventes (acheteur, prix d'adjudication, quittance, PV de vente).
 */
#[Title("Groupes d'enchères — Fourrière")]
class Groupes extends AppPage
{
    use WithFileUploads;

    public const ROUTE = 'fourriere.groupes';

    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = ['label' => '', 'auction_on' => '', 'place' => '', 'vehicle_type' => '', 'opening_price' => '', 'notes' => ''];

    public ?int $saleGroupId = null;

    public array $sale = [];

    public $report = null;

    public function create(): void
    {
        $this->authorizeWrite('fourriere.modifier');
        $this->editingId = null;
        $this->form = ['label' => '', 'auction_on' => '', 'place' => '', 'vehicle_type' => '', 'opening_price' => '', 'notes' => ''];
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorizeWrite('fourriere.modifier');
        $g = AuctionGroup::findOrFail($id);
        $this->editingId = $g->id;
        $this->form = ['label' => $g->label, 'auction_on' => $g->auction_on?->toDateString() ?? '', 'place' => (string) $g->place,
            'vehicle_type' => (string) $g->vehicle_type, 'opening_price' => (string) (float) $g->opening_price, 'notes' => (string) $g->notes];
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(ImpoundService $service): void
    {
        $this->authorizeWrite('fourriere.modifier');
        $data = $this->validate([
            'form.label' => ['required', 'string', 'max:255'],
            'form.auction_on' => ['nullable', 'date'],
            'form.place' => ['nullable', 'string', 'max:255'],
            'form.vehicle_type' => ['nullable', 'string', 'max:60'],
            'form.opening_price' => ['nullable', 'numeric', 'min:0'],
            'form.notes' => ['nullable', 'string', 'max:1000'],
        ], [], ['form.label' => 'libellé', 'form.opening_price' => "prix d'ouverture"])['form'];
        $data = array_map(fn ($v) => $v === '' ? null : $v, $data);
        $data['opening_price'] ??= 0;
        if ($this->editingId) {
            AuctionGroup::findOrFail($this->editingId)->update($data);
            $this->notify('Groupe mis à jour.');
        } else {
            $g = $service->createGroup($data, auth()->user());
            $this->notify("Groupe {$g->number} créé.");
        }
        $this->showForm = false;
    }

    public function removeVehicle(int $entryId): void
    {
        $this->authorizeWrite('fourriere.modifier');
        $entry = AuctionGroupVehicle::findOrFail($entryId);
        if ($entry->impoundCase?->sale) {
            $this->notify('Véhicule déjà vendu : il ne peut plus être retiré.', 'error');

            return;
        }
        $entry->delete();
        $this->notify('Véhicule retiré du groupe.');
    }

    public function openSale(int $groupId, int $caseId): void
    {
        $this->authorizeWrite('fourriere.modifier');
        $this->saleGroupId = $groupId;
        $this->sale = ['impound_case_id' => $caseId, 'buyer_name' => '', 'buyer_cin' => '', 'buyer_phone' => '', 'hammer_price' => '', 'receipt_number' => '', 'notes' => ''];
        $this->reset('report');
        $this->resetValidation();
    }

    public function saveSale(ImpoundService $service): void
    {
        $this->authorizeWrite('fourriere.modifier');
        $data = $this->validate([
            'sale.impound_case_id' => ['required', 'exists:impound_cases,id'],
            'sale.buyer_name' => ['required', 'string', 'max:255'],
            'sale.buyer_cin' => ['nullable', 'string', 'max:30'],
            'sale.buyer_phone' => ['nullable', 'string', 'max:40'],
            'sale.hammer_price' => ['required', 'numeric', 'min:0'],
            'sale.receipt_number' => ['nullable', 'string', 'max:40'],
            'sale.notes' => ['nullable', 'string', 'max:1000'],
            'report' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
        ], [], ['sale.buyer_name' => 'acheteur', 'sale.hammer_price' => "prix d'adjudication", 'report' => 'PV de vente'])['sale'];
        $caseId = (int) $data['impound_case_id'];
        unset($data['impound_case_id']);
        $data = array_map(fn ($v) => $v === '' ? null : $v, $data);
        if ($this->report) {
            $data['report_path'] = $this->report->store('fourriere/pv', 'local');
            $data['report_name'] = $this->report->getClientOriginalName();
        }
        try {
            $service->sell(AuctionGroup::findOrFail($this->saleGroupId), ImpoundCase::findOrFail($caseId), $data, auth()->user());
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }
        $this->saleGroupId = null;
        $this->notify('Vente enregistrée — véhicule sorti.');
    }

    public function render(ImpoundCalculator $calc)
    {
        return view('livewire.fourriere.groupes', [
            'groups' => AuctionGroup::with(['vehicles.impoundCase.sale', 'sales'])->orderByDesc('id')->get(),
            'types' => $calc->vehicleTypes(),
        ]);
    }
}
