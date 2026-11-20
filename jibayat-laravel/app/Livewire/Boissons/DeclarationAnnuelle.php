<?php

namespace App\Livewire\Boissons;

use App\Domain\Boissons\Models\BeverageOutlet;
use App\Domain\Boissons\Services\BeverageCalculator;
use App\Livewire\AppPage;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

/**
 * Déclaration annuelle — Flask /debits-boissons/<id>/declaration-annuelle : récapitulatif des 4 trimestres déjà
 * déclarés pour une année (par défaut N-1), avec impression du formulaire annuel.
 */
#[Title('Déclaration annuelle — Débits de boissons')]
class DeclarationAnnuelle extends AppPage
{
    public const ROUTE = 'boissons.declaration-annuelle';

    #[Url(as: 'etablissement')]
    public ?int $outletId = null;

    #[Url(as: 'annee')]
    public ?int $year = null;

    #[Url(as: 'q')]
    public string $search = '';

    /** Lignes T1..T4 de l'année (Flask : declarations du module pour l'année, tous statuts). */
    public static function summary(BeverageOutlet $outlet, int $year): array
    {
        $decls = $outlet->declarations()->where('module', BeverageCalculator::MODULE)->where('year', $year)->whereNull('cancelled_at')
            ->where('status', '!=', 'annule')->get()->keyBy('quarter');
        $rows = [];
        $totals = ['base' => 0.0, 'principal' => 0.0, 'pen' => 0.0, 'total' => 0.0];
        foreach ([1, 2, 3, 4] as $q) {
            $d = $decls->get($q);
            $pen = $d ? (float) $d->late_penalty + (float) $d->surcharge + (float) $d->non_filing_fine : 0.0;
            $rows[$q] = ['declaration' => $d, 'pen' => $pen];
            if ($d) {
                $totals['base'] += (float) $d->base_amount;
                $totals['principal'] += (float) $d->principal;
                $totals['pen'] += $pen;
                $totals['total'] += (float) $d->total;
            }
        }

        return ['rows' => $rows, 'totals' => $totals, 'paid' => $decls->where('status', 'paye')->count(),
            'number' => sprintf('DA-%d-%04d', $year, $outlet->id)];
    }

    public function render(BeverageCalculator $calc)
    {
        $this->year ??= now()->year - 1;
        $outlet = $this->outletId ? BeverageOutlet::with(['taxpayer', 'tariff'])->find($this->outletId) : null;
        if (! $outlet) {
            $s = $this->search;

            return view('livewire.boissons.fiche-choose', [
                'outlets' => BeverageOutlet::with('taxpayer')->where('is_active', true)
                    ->when($s, fn ($q) => $q->where(fn ($w) => $w->where('number', 'like', "%{$s}%")->orWhere('name', 'like', "%{$s}%")
                        ->orWhereHas('taxpayer', fn ($t) => $t->where('last_name', 'like', "%{$s}%")->orWhere('cin', 'like', "%{$s}%"))))
                    ->orderBy('name')->limit(100)->get(),
            ]);
        }

        return view('livewire.boissons.declaration-annuelle', ['outlet' => $outlet, 'rate' => $calc->rateForOutlet($outlet)] + self::summary($outlet, $this->year));
    }
}
