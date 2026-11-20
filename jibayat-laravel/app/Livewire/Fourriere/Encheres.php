<?php

namespace App\Livewire\Fourriere;

use App\Domain\Fourriere\Models\AuctionGroup;
use App\Domain\Fourriere\Models\AuctionGroupVehicle;
use App\Domain\Fourriere\Models\ImpoundCase;
use App\Domain\Fourriere\Models\ImpoundSetting;
use App\Domain\Fourriere\Services\ImpoundCalculator;
use App\Livewire\AppPage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;

/** Véhicules aliénables — Flask /fourriere/encheres : plus de 365 jours en fourrière, à inscrire dans un groupe d'enchères. */
#[Title('Véhicules aliénables — Fourrière')]
class Encheres extends AppPage
{
    public const ROUTE = 'fourriere.encheres';

    public array $groupFor = [];

    public array $conditionFor = [];

    public function addToGroup(int $caseId): void
    {
        $this->authorizeWrite('fourriere.modifier');
        $this->validate([
            "groupFor.{$caseId}" => ['required', Rule::exists('auction_groups', 'id')->where('status', 'ouvert')],
            "conditionFor.{$caseId}" => ['nullable', 'string', 'max:20'],
        ], [], ["groupFor.{$caseId}" => 'groupe']);
        if (AuctionGroupVehicle::where('impound_case_id', $caseId)->exists()) {
            $this->notify('Ce véhicule est déjà dans un groupe.', 'error');

            return;
        }
        $gid = (int) $this->groupFor[$caseId];
        AuctionGroupVehicle::create(['auction_group_id' => $gid, 'impound_case_id' => $caseId,
            'condition' => $this->conditionFor[$caseId] ?? 'moyen', 'sort_order' => AuctionGroupVehicle::where('auction_group_id', $gid)->count() + 1]);
        $this->notify('Véhicule ajouté au groupe '.AuctionGroup::find($gid)?->number.'.');
    }

    public function render()
    {
        $grouped = AuctionGroupVehicle::pluck('impound_case_id')->flip();
        $rows = ImpoundCase::where('is_active', true)->where('status', '!=', 'sortie')->orderBy('impounded_on')->get()
            ->map(fn ($c) => ['case' => $c] + ImpoundCalculator::enrich($c))
            ->filter(fn ($r) => $r['days'] > 365);

        return view('livewire.fourriere.encheres', [
            'rows' => $rows, 'grouped' => $grouped,
            'groups' => AuctionGroup::where('status', 'ouvert')->orderByDesc('id')->get(),
            'conditions' => ImpoundSetting::where('category', 'etat_vehicule')->where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }
}
