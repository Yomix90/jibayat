<?php

namespace App\Livewire\Boissons;

use App\Domain\Boissons\Models\BeverageOutlet;
use App\Domain\Declarations\Services\DeclarationService;
use App\Livewire\AppPage;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

/**
 * Cessation d'activité — Flask /debits-boissons/<id>/cessation : établissements en activité ou en cessation,
 * L'arrêt et la reprise passent par une déclaration d'assiette (art. 68) signée : motif, date, reprise prévue ; période exonérée.
 */
#[Title('Cessation — Débits de boissons')]
class Cessation extends AppPage
{
    public const ROUTE = 'boissons.cessation';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'statut')]
    public string $status = 'actif';

    public function render()
    {
        $s = $this->search;

        $service = app(DeclarationService::class);
        $outlets = BeverageOutlet::with('taxpayer')->where('is_active', true)
                ->when(in_array($this->status, ['actif', 'cesse'], true), fn ($q) => $q->where('status', $this->status))
                ->when($s, fn ($q) => $q->where(fn ($w) => $w->where('number', 'like', "%{$s}%")->orWhere('name', 'like', "%{$s}%")
                    ->orWhereHas('taxpayer', fn ($t) => $t->where('last_name', 'like', "%{$s}%")->orWhere('cin', 'like', "%{$s}%"))))
                ->orderBy('name')->get();

        return view('livewire.boissons.cessation', [
            'stops' => $outlets->where('status', 'cesse')->mapWithKeys(fn ($o) => [$o->id => $service->openCessation('DEBITS_BOISSONS', $o)])->filter()->all(),
            'outlets' => $outlets,
        ]);
    }
}
