<?php

namespace App\Livewire\Regie;

use App\Domain\Regie\Models\CashEntry;
use App\Domain\Regie\Services\EntryService;
use App\Domain\Regie\Services\Ledger;
use App\Domain\Regie\Services\MonthLock;
use App\Livewire\AppPage;
use App\Models\Rubric;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

/**
 * Saisie des recettes du jour, ligne par ligne (rubrique, mode de paiement, quantité × prix ou montant,
 * référence, partie versante). Les lignes sont enregistrées en brouillon ; le régisseur les valide ensuite.
 */
#[Title('Saisie des recettes')]
class Saisie extends AppPage
{
    public const ROUTE = 'regie.saisie';

    #[Url(as: 'date')]
    public string $date = '';

    public array $lines = [];

    public function mount(): void
    {
        $this->date = $this->date ?: today()->toDateString();
        $this->lines = [self::blank()];
    }

    public static function blank(?array $from = null): array
    {
        return ['rubric_id' => $from['rubric_id'] ?? '', 'payment_mode' => $from['payment_mode'] ?? 'especes', 'quantity' => '', 'unit_price' => $from['unit_price'] ?? '',
            'amount' => '', 'reference' => '', 'payer' => '', 'description' => ''];
    }

    public function addLine(): void
    {
        $this->lines[] = self::blank(end($this->lines) ?: null);
    }

    public function removeLine(int $i): void
    {
        unset($this->lines[$i]);
        $this->lines = array_values($this->lines) ?: [self::blank()];
    }

    public function save(EntryService $service, bool $validate = false): void
    {
        $this->authorizeWrite('recettes.ajouter');
        $this->validate(['date' => ['required', 'date', 'before_or_equal:today']], [], ['date' => 'date']);
        $entries = $service->createDrafts($this->date, $this->lines, auth()->user());
        if ($validate && $this->allowed('paiements.valider')) {
            $service->validate(array_map(fn ($e) => $e->id, $entries), auth()->user());
            $this->notify(count($entries).' recette(s) enregistrée(s) et validée(s).');
        } else {
            $this->notify(count($entries).' recette(s) enregistrée(s) en brouillon : à valider par le régisseur.');
        }
        $this->lines = [self::blank()];
        $this->resetValidation();
    }

    public function saveAndValidate(EntryService $service): void
    {
        $this->authorizeWrite('paiements.valider');
        $this->save($service, true);
    }

    public function render()
    {
        $rubrics = Rubric::where('is_active', true)->where('manual_entry', true)->orderBy('code')->get(['id', 'code', 'label', 'label_ar']);
        $total = 0.0;
        foreach ($this->lines as $l) {
            $total += is_numeric($l['quantity']) && is_numeric($l['unit_price']) ? (float) $l['quantity'] * (float) $l['unit_price'] : (is_numeric($l['amount']) ? (float) $l['amount'] : 0);
        }

        return view('livewire.regie.saisie', [
            'rubrics' => $rubrics, 'total' => round($total, 2), 'locked' => $this->date && MonthLock::isClosed($this->date),
            'day' => CashEntry::with(['rubric', 'author'])->whereDate('entry_date', $this->date ?: today())->orderByDesc('id')->get(),
            'modes' => array_intersect_key(Ledger::MODES, array_flip(Ledger::ENTRY_MODES)),
        ]);
    }
}
