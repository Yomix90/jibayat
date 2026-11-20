<?php

namespace App\Livewire\Regie;

use App\Domain\Regie\Models\CashClosure;
use App\Domain\Regie\Services\Ledger;
use App\Domain\Regie\Services\MonthLock;
use App\Livewire\AppPage;
use App\Livewire\Regie\Concerns\HasPeriod;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Title;

/**
 * Clôture mensuelle : verrou du mois. Avant la clôture, les points bloquants sont listés (brouillons,
 * mois précédent ouvert, mois non terminé). La clôture fige l'état mensuel (Modèle 10) et le solde.
 * La réouverture est motivée et réservée à l'administration.
 */
#[Title('Clôture mensuelle')]
class Cloture extends AppPage
{
    use HasPeriod;

    public const ROUTE = 'regie.cloture';

    public ?int $reopenId = null;

    public string $reopenReason = '';

    public function close(int $month, MonthLock $lock): void
    {
        $this->authorizeWrite('paiements.valider');
        try {
            $lock->close($this->year, $month, auth()->user());
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->notify(collect($e->errors())->flatten()->first(), 'error');

            return;
        }
        $this->notify(sprintf('%s %d clôturé.', Ledger::MONTHS[$month], $this->year));
    }

    public function askReopen(int $id): void
    {
        $this->authorizeWrite('config.gerer');
        $this->reopenId = $id;
        $this->reopenReason = '';
        $this->resetValidation();
    }

    public function confirmReopen(MonthLock $lock): void
    {
        $this->authorizeWrite('config.gerer');
        $this->validate(['reopenReason' => ['required', 'string', 'min:5', 'max:255']], [], ['reopenReason' => 'motif']);
        try {
            $lock->reopen(CashClosure::findOrFail($this->reopenId), auth()->user(), $this->reopenReason);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->addError('reopenReason', collect($e->errors())->flatten()->first());

            return;
        }
        $this->reopenId = null;
        $this->notify('Mois rouvert : la réouverture est inscrite au journal.', 'warning');
    }

    public function render(Ledger $ledger, MonthLock $lock)
    {
        $months = [];
        $today = CarbonImmutable::today();
        foreach (range(1, 12) as $m) {
            $start = CarbonImmutable::create($this->year, $m, 1);
            if ($start->gt($today)) {
                break;
            }
            $closure = MonthLock::closure($this->year, $m);
            $months[] = ['month' => $m, 'closure' => $closure,
                'book' => $closure ? null : $ledger->cashBook($this->year, $m),
                'blockers' => $closure ? [] : $lock->blockers($this->year, $m),
                'history' => CashClosure::with('closer')->where('year', $this->year)->where('month', $m)->whereNotNull('reopened_at')->get()];
        }

        return view('livewire.regie.cloture', ['months' => array_reverse($months)]);
    }
}
