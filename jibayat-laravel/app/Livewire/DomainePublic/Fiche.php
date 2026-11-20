<?php

namespace App\Livewire\DomainePublic;

use App\Domain\DomainePublic\Models\PublicOccupation;
use App\Domain\DomainePublic\Services\OccupationCalculator;
use App\Domain\DomainePublic\Services\OccupationService;
use App\Livewire\AppPage;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

/** Fiche occupation — Flask /occupation-domaine/<id> : occupant, autorisation, redevance, situation, liquidations, renouvellement. */
#[Title('Fiche occupation — Domaine public')]
class Fiche extends AppPage
{
    public const ROUTE = 'domaine-public.fiche';

    #[Url(as: 'occupation')]
    public ?int $occupationId = null;

    #[Url(as: 'q')]
    public string $search = '';

    public function renew(string $duration, OccupationService $service): void
    {
        $this->authorizeWrite('domaine_public.modifier');
        abort_unless(array_key_exists($duration, OccupationService::DURATIONS), 422);
        $until = $service->renew(PublicOccupation::findOrFail($this->occupationId), $duration, auth()->user());
        $this->notify('Autorisation renouvelée jusqu’au '.\Illuminate\Support\Carbon::parse($until)->format('d/m/Y').'.');
    }

    public function render(OccupationCalculator $calc)
    {
        $o = $this->occupationId ? PublicOccupation::with('taxpayer')->find($this->occupationId) : null;
        if (! $o) {
            $s = $this->search;

            return view('livewire.domaine-public.fiche-choose', [
                'occupations' => PublicOccupation::with('taxpayer')
                    ->when($s, fn ($q) => $q->where(fn ($w) => $w->where('number', 'like', "%{$s}%")->orWhere('location', 'like', "%{$s}%")
                        ->orWhereHas('taxpayer', fn ($t) => $t->where('last_name', 'like', "%{$s}%")->orWhere('cin', 'like', "%{$s}%"))))
                    ->orderByDesc('is_active')->orderBy('number')->limit(100)->get(),
            ]);
        }
        $situation = $calc->situation($o);

        return view('livewire.domaine-public.fiche', [
            'occupation' => $o, 'situation' => $situation,
            'period' => OccupationCalculator::periodLabel($situation['quarters']),
            'periodAr' => OccupationCalculator::periodLabel($situation['quarters'], true),
            'history' => DB::table('tax_declarations as d')
                ->leftJoin('payment_slips as s', fn ($j) => $j->on('s.tax_declaration_id', '=', 'd.id')->whereNull('s.cancelled_at'))
                ->where('d.module', OccupationCalculator::MODULE)->where('d.taxable_type', 'public_occupation')->where('d.taxable_id', $o->id)
                ->orderByDesc('d.year')->orderByDesc('d.quarter')->orderByDesc('d.id')
                ->get(['d.id', 'd.number', 'd.year', 'd.quarter', 'd.total', 'd.status', 'd.declared_on', 'd.cancelled_at', 'd.cancellation_reason',
                    's.slip_number', 's.status as slip_status', 's.receipt_number', 's.rejection_reason']),
        ]);
    }
}
