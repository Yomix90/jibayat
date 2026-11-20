<?php

namespace App\Livewire\Regie\Concerns;

use Livewire\Attributes\Url;

/** Sélecteur année / mois des états de la régie (?annee=2026&mois=11). */
trait HasPeriod
{
    #[Url(as: 'annee')]
    public int $year = 0;

    #[Url(as: 'mois')]
    public int $month = 0;

    public function mountHasPeriod(): void
    {
        $this->year = $this->year ?: (int) now()->year;
        $this->month = $this->month >= 1 && $this->month <= 12 ? $this->month : (int) now()->month;
    }

    public function previousMonth(): void
    {
        $d = now()->setDate($this->year, $this->month, 1)->subMonth();
        [$this->year, $this->month] = [(int) $d->year, (int) $d->month];
    }

    public function nextMonth(): void
    {
        $d = now()->setDate($this->year, $this->month, 1)->addMonth();
        [$this->year, $this->month] = [(int) $d->year, (int) $d->month];
    }

    public function years(): array
    {
        return range((int) now()->year, max(2020, (int) now()->year - 8));
    }
}
