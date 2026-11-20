<?php

namespace App\Livewire\Locations;

use App\Domain\Locations\Models\Lease;
use App\Domain\Locations\Services\LeaseCalculator;
use App\Livewire\AppPage;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

/**
 * Fiche locataire / bail — Flask : /location-locaux/<id> (identité, local, tarif, situation, déclarations).
 */
#[Title('Fiche locataire')]
class Fiche extends AppPage
{
    public const ROUTE = 'locations.fiche';

    #[Url(as: 'bail')]
    public ?int $leaseId = null;

    #[Url(as: 'q')]
    public string $search = '';

    public function render(LeaseCalculator $calc)
    {
        $lease = $this->leaseId ? Lease::with(['taxpayer', 'shop', 'leaseTariff.tariff', 'leaseSector'])->find($this->leaseId) : null;
        if (! $lease) {
            return view('livewire.locations.fiche-choose', [
                'leases' => Lease::with(['taxpayer', 'shop'])
                    ->when($this->search, fn ($q) => $q->where(fn ($w) => $w->where('number', 'like', "%{$this->search}%")
                        ->orWhere('premises_ref', 'like', "%{$this->search}%")
                        ->orWhereHas('taxpayer', fn ($t) => $t->where('last_name', 'like', "%{$this->search}%")->orWhere('cin', 'like', "%{$this->search}%"))))
                    ->orderByDesc('is_active')->orderBy('number')->limit(100)->get(),
            ]);
        }

        $situation = $calc->situation($lease);

        return view('livewire.locations.fiche', [
            'lease' => $lease,
            'rent' => $calc->monthlyRent($lease),
            'history' => $calc->tariffHistory($lease),
            'situation' => $situation,
            'periodAr' => LeaseCalculator::periodLabel($situation['months'], true),
            'declarations' => DB::table('tax_declarations as d')
                ->leftJoin('payment_slips as s', fn ($j) => $j->on('s.tax_declaration_id', '=', 'd.id')->whereNull('s.cancelled_at'))
                ->where('d.taxable_type', 'lease')->where('d.taxable_id', $lease->id)
                ->orderByDesc('d.year')->orderByDesc('d.id')
                ->get(['d.id', 'd.number', 'd.year', 'd.total', 'd.status', 'd.notes', 'd.declared_on', 'd.paid_on',
                    's.slip_number', 's.receipt_number', 's.status as slip_status']),
        ]);
    }
}
