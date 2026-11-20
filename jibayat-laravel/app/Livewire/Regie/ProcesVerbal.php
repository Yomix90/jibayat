<?php

namespace App\Livewire\Regie;

use App\Domain\Regie\Models\CashReport;
use App\Domain\Regie\Services\MinutesService;
use App\Livewire\AppPage;
use App\Models\Setting;
use Livewire\WithPagination;

/**
 * Écran commun des procès-verbaux (arrêté, vérification, récolement, remise de service) :
 * nouvel établissement (solde théorique du livre de caisse, billetage, stock compté, intervenants),
 * liste, impression, annulation motivée.
 */
abstract class ProcesVerbal extends AppPage
{
    use WithPagination;

    public const TYPE = '';

    public bool $showForm = false;

    public string $date = '';

    public array $bills = [];

    public string $cheques = '';

    public array $counts = [];

    public array $people = [];

    public string $notes = '';

    public ?int $cancelId = null;

    public string $cancelReason = '';

    public function openForm(): void
    {
        $this->authorizeWrite('paiements.valider');
        $this->date = static::TYPE === 'recolement' || static::TYPE === 'verification' ? now()->lastOfQuarter()->min(today())->toDateString()
            : (static::TYPE === 'arrete' ? now()->subMonthNoOverflow()->endOfMonth()->toDateString() : today()->toDateString());
        $this->bills = array_fill_keys(MinutesService::DENOMINATIONS, '');
        $this->cheques = '';
        $this->counts = [];
        $this->people = ['regisseur' => (string) Setting::get('regie.regisseur', auth()->user()->name), 'verifier' => '', 'quality' => '', 'outgoing' => (string) Setting::get('regie.regisseur', ''), 'incoming' => ''];
        $this->notes = '';
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(MinutesService $service): void
    {
        $this->authorizeWrite('paiements.valider');
        $this->validate(['date' => ['required', 'date', 'before_or_equal:today'], 'bills.*' => ['nullable', 'integer', 'min:0'], 'cheques' => ['nullable', 'numeric', 'min:0'],
            'counts.*' => ['nullable', 'integer', 'min:0'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $r = $service->create(static::TYPE, ['report_date' => $this->date, 'bills' => $this->bills, 'cheques' => $this->cheques ?: 0,
            'counts' => $this->counts, 'people' => $this->people, 'notes' => $this->notes], auth()->user());
        $this->showForm = false;
        $this->notify("{$r->number} établi.".((float) $r->difference != 0 ? ' Écart constaté : '.number_format((float) $r->difference, 2, ',', ' ').' DH.' : ''));
    }

    public function askCancel(int $id): void
    {
        $this->authorizeWrite('paiements.valider');
        $this->cancelId = $id;
        $this->cancelReason = '';
        $this->resetValidation();
    }

    public function confirmCancel(): void
    {
        $this->authorizeWrite('paiements.valider');
        $this->validate(['cancelReason' => ['required', 'string', 'min:5', 'max:255']], [], ['cancelReason' => 'motif']);
        $r = CashReport::findOrFail($this->cancelId);
        if (! $r->isCancelled()) {
            $r->cancel(auth()->user(), $this->cancelReason);
        }
        $this->cancelId = null;
        $this->notify('Procès-verbal annulé ; il reste visible avec son motif.', 'warning');
    }

    public function render(MinutesService $service)
    {
        $type = MinutesService::TYPES[static::TYPE];
        $theory = $this->showForm && $this->date ? $service->theoretical(static::TYPE, $this->date) : null;
        $counted = 0.0;
        foreach ($this->bills as $den => $n) {
            $counted += (int) $n * (float) $den;
        }

        return view('livewire.regie.proces-verbal', [
            'type' => static::TYPE, 't' => $type, 'theory' => $theory, 'counted' => round($counted + (float) ($this->cheques ?: 0), 2),
            'rows' => CashReport::with('author')->where('type', static::TYPE)->orderByDesc('report_date')->orderByDesc('id')->paginate(20),
        ]);
    }
}
