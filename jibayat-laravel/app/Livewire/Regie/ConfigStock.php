<?php

namespace App\Livewire\Regie;

use App\Domain\Documents\Models\StampValue;
use App\Domain\Regie\Models\StockMovement;
use App\Domain\Regie\Services\StockService;
use App\Livewire\AppPage;
use App\Models\Rubric;
use Livewire\Attributes\Title;

/**
 * Configuration du stock : pour chaque valeur (créée dans Paramètres ➜ Intervenants), la rubrique de recette
 * (tickets vendus), le seuil d'alerte, et la dotation annuelle de la réserve TGR.
 */
#[Title('Configuration du stock')]
class ConfigStock extends AppPage
{
    public const ROUTE = 'regie.config-stock';

    public array $values = [];

    public array $dotation = [];

    public function mount(): void
    {
        $this->load();
        $this->dotation = ['stamp_value_id' => (string) StampValue::where('is_active', true)->value('id'), 'quantity' => '', 'moved_on' => today()->toDateString(), 'reference' => ''];
    }

    protected function load(): void
    {
        $this->values = StampValue::orderBy('label')->get()->mapWithKeys(fn ($v) => [$v->id => [
            'type' => (string) $v->type, 'rubric_id' => (string) $v->rubric_id, 'alert_threshold' => (string) (int) $v->alert_threshold,
        ]])->all();
    }

    public function save(): void
    {
        $this->authorizeWrite('regie.modifier');
        $this->validate(['values.*.type' => ['required', 'in:timbre,vignette,ticket'], 'values.*.rubric_id' => ['nullable', 'exists:rubrics,id'],
            'values.*.alert_threshold' => ['required', 'integer', 'min:0']]);
        foreach ($this->values as $id => $v) {
            StampValue::whereKey($id)->update(['type' => $v['type'], 'rubric_id' => $v['rubric_id'] ?: null, 'alert_threshold' => (int) $v['alert_threshold']]);
        }
        activity('regie')->causedBy(auth()->user())->log('Configuration du stock');
        $this->notify('Configuration du stock enregistrée.');
    }

    public function addDotation(StockService $service): void
    {
        $this->authorizeWrite('regie.modifier');
        $this->validate(['dotation.stamp_value_id' => ['required', 'exists:stamp_values,id'], 'dotation.quantity' => ['required', 'integer', 'min:1'],
            'dotation.moved_on' => ['required', 'date'], 'dotation.reference' => ['nullable', 'string', 'max:60']], [], ['dotation.quantity' => 'quantité']);
        $service->move((int) $this->dotation['stamp_value_id'], 'dotation', (int) $this->dotation['quantity'], $this->dotation['moved_on'],
            $this->dotation['reference'], 'Dotation de la réserve TGR', auth()->user());
        $this->dotation['quantity'] = '';
        $this->dotation['reference'] = '';
        $this->notify('Dotation de la réserve TGR enregistrée.');
    }

    public function render()
    {
        return view('livewire.regie.config-stock', [
            'stampValues' => StampValue::orderBy('label')->get()->keyBy('id'),
            'rubrics' => Rubric::where('manual_entry', true)->orderBy('code')->get(['id', 'code', 'label']),
            'dotations' => StockMovement::with('value')->where('movement', 'dotation')->orderByDesc('moved_on')->limit(20)->get(),
        ]);
    }
}
