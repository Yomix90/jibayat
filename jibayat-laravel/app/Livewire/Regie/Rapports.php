<?php

namespace App\Livewire\Regie;

use App\Domain\Regie\Services\Ledger;
use App\Livewire\AppPage;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

/** Rapports détaillés sur une période au choix : synthèse par famille et mode, détail des pièces, versements. */
#[Title('Rapports détaillés')]
class Rapports extends AppPage
{
    public const ROUTE = 'regie.rapports';

    #[Url(as: 'du')]
    public string $from = '';

    #[Url(as: 'au')]
    public string $to = '';

    #[Url(as: 'rubrique')]
    public string $rubric = '';

    public function mount(): void
    {
        $this->from = $this->from ?: now()->startOfMonth()->toDateString();
        $this->to = $this->to ?: today()->toDateString();
    }

    public function preset(string $p): void
    {
        [$this->from, $this->to] = match ($p) {
            'today' => [today()->toDateString(), today()->toDateString()],
            'week' => [now()->startOfWeek()->toDateString(), today()->toDateString()],
            'quarter' => [now()->firstOfQuarter()->toDateString(), today()->toDateString()],
            'year' => [now()->startOfYear()->toDateString(), today()->toDateString()],
            default => [now()->startOfMonth()->toDateString(), today()->toDateString()],
        };
    }

    public static function report(Ledger $ledger, string $from, string $to, ?int $rubric = null): array
    {
        $receipts = $ledger->receipts($from, $to, true)->when($rubric, fn ($c) => $c->where('rubric_id', $rubric))->values();

        return [
            'receipts' => $receipts, 'recap' => $ledger->recap($from, $to),
            'remittances' => $ledger->remittances($from, $to), 'opening' => $ledger->balanceAt(\Carbon\Carbon::parse($from)->subDay()->toDateString()),
            'closing' => $ledger->balanceAt($to),
        ];
    }

    public function render(Ledger $ledger)
    {
        $from = $this->from ?: today()->toDateString();
        $to = max($from, $this->to ?: today()->toDateString());

        return view('livewire.regie.rapports', self::report($ledger, $from, $to, $this->rubric ? (int) $this->rubric : null)
            + ['rubrics' => \App\Models\Rubric::orderBy('code')->get(['id', 'code', 'label'])]);
    }
}
