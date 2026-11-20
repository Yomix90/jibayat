<?php

namespace App\Livewire\Transport;

use App\Domain\Boissons\Services\BeverageCalculator;
use App\Domain\Transport\Models\Vehicle;
use App\Domain\Transport\Services\TransportCalculator;
use App\Livewire\AppPage;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

/** Fiche véhicule — Flask /stationnement/<id> : propriétaire, véhicule, tarifs, situation, paiements. */
#[Title('Fiche véhicule')]
class Fiche extends AppPage
{
    public const ROUTE = 'transport.fiche';

    #[Url(as: 'vehicule')]
    public ?int $vehicleId = null;

    #[Url(as: 'q')]
    public string $search = '';

    public function render(TransportCalculator $calc)
    {
        $vehicle = $this->vehicleId ? Vehicle::with('taxpayer')->find($this->vehicleId) : null;
        if (! $vehicle) {
            $s = $this->search;

            return view('livewire.transport.fiche-choose', [
                'vehicles' => Vehicle::with('taxpayer')
                    ->when($s, fn ($q) => $q->where(fn ($w) => $w->where('registration', 'like', "%{$s}%")->orWhere('number', 'like', "%{$s}%")
                        ->orWhereHas('taxpayer', fn ($t) => $t->where('last_name', 'like', "%{$s}%")->orWhere('cin', 'like', "%{$s}%"))))
                    ->orderByDesc('is_active')->orderBy('registration')->limit(100)->get(),
            ]);
        }
        $quarters = $calc->unpaidQuarters($vehicle);

        return view('livewire.transport.fiche', [
            'vehicle' => $vehicle,
            'situation' => $calc->situation($vehicle),
            'quarters' => $quarters,
            'period' => BeverageCalculator::periodLabel($quarters),
            'periodAr' => BeverageCalculator::periodLabel($quarters, true),
            'history' => DB::table('tax_declarations as d')
                ->leftJoin('payment_slips as s', fn ($j) => $j->on('s.tax_declaration_id', '=', 'd.id')->whereNull('s.cancelled_at'))
                ->where('d.module', TransportCalculator::MODULE)->where('d.taxable_type', 'vehicle')->where('d.taxable_id', $vehicle->id)
                ->orderByDesc('d.year')->orderByDesc('d.quarter')->orderByDesc('d.id')
                ->get(['d.id', 'd.number', 'd.year', 'd.quarter', 'd.base_amount', 'd.rate', 'd.total', 'd.status', 'd.declared_on', 'd.cancelled_at', 'd.cancellation_reason',
                    's.slip_number', 's.status as slip_status', 's.receipt_number', 's.rejection_reason']),
        ]);
    }
}
