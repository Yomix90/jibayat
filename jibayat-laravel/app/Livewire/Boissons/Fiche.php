<?php

namespace App\Livewire\Boissons;

use App\Domain\Boissons\Models\BeverageOutlet;
use App\Domain\Boissons\Services\BeverageCalculator;
use App\Livewire\AppPage;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

/**
 * Fiche établissement — Flask /debits-boissons/<id> : exploitant, établissement, situation, déclarations,
 * cessation d'activité, impressions (avis, déclaration annuelle, formulaires).
 */
#[Title('Fiche établissement')]
class Fiche extends AppPage
{
    public const ROUTE = 'boissons.fiche';

    #[Url(as: 'etablissement')]
    public ?int $outletId = null;

    #[Url(as: 'q')]
    public string $search = '';

    /** Flask tdb_cessation : l'établissement passe en « cessation » (comme Flask, les trimestres continuent d'être comptés). */
    public function cease(): void
    {
        $this->authorizeWrite('boissons.modifier');
        $outlet = BeverageOutlet::findOrFail($this->outletId);
        $outlet->update(['status' => $outlet->status === 'cesse' ? 'actif' : 'cesse']);
        activity('parametres')->causedBy(auth()->user())->performedOn($outlet)
            ->log(($outlet->status === 'cesse' ? 'Cessation d’activité' : 'Reprise d’activité')." : {$outlet->name}");
        $this->notify($outlet->status === 'cesse' ? 'Établissement marqué en cessation d’activité.' : 'Établissement remis en activité.');
    }

    public function render(BeverageCalculator $calc)
    {
        $outlet = $this->outletId ? BeverageOutlet::with(['taxpayer', 'tariff'])->find($this->outletId) : null;
        if (! $outlet) {
            $s = $this->search;

            return view('livewire.boissons.fiche-choose', [
                'outlets' => BeverageOutlet::with('taxpayer')
                    ->when($s, fn ($q) => $q->where(fn ($w) => $w->where('number', 'like', "%{$s}%")->orWhere('name', 'like', "%{$s}%")
                        ->orWhereHas('taxpayer', fn ($t) => $t->where('last_name', 'like', "%{$s}%")->orWhere('company_name', 'like', "%{$s}%")->orWhere('cin', 'like', "%{$s}%"))))
                    ->orderByDesc('is_active')->orderBy('name')->limit(100)->get(),
            ]);
        }
        $quarters = $calc->unpaidQuarters($outlet);

        return view('livewire.boissons.fiche', [
            'outlet' => $outlet,
            'situation' => $calc->situation($outlet),
            'quarters' => $quarters,
            'period' => BeverageCalculator::periodLabel($quarters),
            'periodAr' => BeverageCalculator::periodLabel($quarters, true),
            'history' => DB::table('tax_declarations as d')
                ->leftJoin('payment_slips as s', fn ($j) => $j->on('s.tax_declaration_id', '=', 'd.id')->whereNull('s.cancelled_at'))
                ->where('d.module', BeverageCalculator::MODULE)->where('d.taxable_type', 'beverage_outlet')->where('d.taxable_id', $outlet->id)
                ->orderByDesc('d.year')->orderByDesc('d.quarter')->orderByDesc('d.id')
                ->get(['d.id', 'd.number', 'd.year', 'd.quarter', 'd.base_amount', 'd.total', 'd.status', 'd.declared_on', 'd.cancelled_at', 'd.cancellation_reason',
                    's.slip_number', 's.status as slip_status', 's.receipt_number', 's.rejection_reason']),
        ]);
    }
}
