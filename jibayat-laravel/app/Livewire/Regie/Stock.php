<?php

namespace App\Livewire\Regie;

use App\Domain\Documents\Models\StampValue;
use App\Domain\Regie\Models\StockMovement;
use App\Domain\Regie\Services\StockService;
use App\Livewire\AppPage;
use Livewire\Attributes\Title;

/**
 * Stock GIR des tickets et vignettes : réserve TGR, stock de la régie, chez les agents, consommé ;
 * mouvements (retrait TGR ➜ régie, ajustements) avec annulation motivée.
 * Les paquets de vignettes reçus (États & documents ➜ Paquets) comptent comme retraits de la réserve TGR.
 */
#[Title('Stock GIR')]
class Stock extends AppPage
{
    public const ROUTE = 'regie.stock';

    public bool $showMove = false;

    public array $move = [];

    public ?int $cancelId = null;

    public string $cancelReason = '';

    public function openMove(string $movement = 'retrait', ?int $valueId = null): void
    {
        $this->authorizeWrite('regie.modifier');
        $this->move = ['stamp_value_id' => (string) ($valueId ?? StampValue::where('is_active', true)->value('id')), 'movement' => $movement,
            'quantity' => '', 'moved_on' => today()->toDateString(), 'reference' => '', 'notes' => ''];
        $this->resetValidation();
        $this->showMove = true;
    }

    public function saveMove(StockService $service): void
    {
        $this->authorizeWrite('regie.modifier');
        $this->validate(['move.stamp_value_id' => ['required', 'exists:stamp_values,id'], 'move.movement' => ['required', 'in:'.implode(',', array_keys(StockService::MOVEMENTS))],
            'move.quantity' => ['required', 'integer', 'not_in:0'], 'move.moved_on' => ['required', 'date'], 'move.reference' => ['nullable', 'string', 'max:60'],
            'move.notes' => ['nullable', 'string', 'max:255']], [], ['move.quantity' => 'quantité']);
        try {
            $service->move((int) $this->move['stamp_value_id'], $this->move['movement'], (int) $this->move['quantity'], $this->move['moved_on'],
                $this->move['reference'], $this->move['notes'], auth()->user());
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw \Illuminate\Validation\ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn ($m, $k) => ['move.'.$k => $m])->all());
        }
        $this->showMove = false;
        $this->notify('Mouvement de stock enregistré.');
    }

    public function askCancel(int $id): void
    {
        $this->authorizeWrite('regie.modifier');
        $this->cancelId = $id;
        $this->cancelReason = '';
        $this->resetValidation();
    }

    public function confirmCancel(): void
    {
        $this->authorizeWrite('regie.modifier');
        $this->validate(['cancelReason' => ['required', 'string', 'min:5', 'max:255']], [], ['cancelReason' => 'motif']);
        $m = StockMovement::findOrFail($this->cancelId);
        if (! $m->isCancelled()) {
            $m->cancel(auth()->user(), $this->cancelReason);
        }
        $this->cancelId = null;
        $this->notify('Mouvement annulé.', 'warning');
    }

    public function render(StockService $service)
    {
        return view('livewire.regie.stock', [
            'state' => $service->state(), 'values' => StampValue::where('is_active', true)->orderBy('label')->get(),
            'movements' => StockMovement::with('value')->orderByDesc('moved_on')->orderByDesc('id')->limit(40)->get(),
        ]);
    }
}
