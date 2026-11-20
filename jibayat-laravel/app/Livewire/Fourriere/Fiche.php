<?php

namespace App\Livewire\Fourriere;

use App\Domain\Fourriere\Models\ImpoundCase;
use App\Domain\Fourriere\Services\ImpoundCalculator;
use App\Domain\Fourriere\Services\ImpoundService;
use App\Livewire\AppPage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

/** Fiche dossier de fourrière — Flask /fourriere/<id> : véhicule, garde, montant dû, propriétaire, paiements, sortie. */
#[Title('Fiche dossier — Fourrière')]
class Fiche extends AppPage
{
    public const ROUTE = 'fourriere.fiche';

    #[Url(as: 'dossier')]
    public ?int $caseId = null;

    #[Url(as: 'q')]
    public string $search = '';

    public function release(ImpoundService $service): void
    {
        $this->authorizeWrite('fourriere.modifier');
        try {
            $service->release(ImpoundCase::findOrFail($this->caseId), auth()->user());
        } catch (ValidationException $e) {
            $this->notify($e->validator->errors()->first(), 'error');

            return;
        }
        $this->notify('Sortie validée — véhicule restitué.');
    }

    public static function payments(ImpoundCase $c)
    {
        return DB::table('tax_declarations as d')
            ->leftJoin('payment_slips as s', fn ($j) => $j->on('s.tax_declaration_id', '=', 'd.id')->whereNull('s.cancelled_at'))
            ->where('d.module', ImpoundCalculator::MODULE)->where('d.taxable_type', 'impound_case')->where('d.taxable_id', $c->id)
            ->orderByDesc('d.id')
            ->get(['d.id', 'd.number', 'd.total', 'd.status', 'd.notes', 'd.declared_on', 'd.cancelled_at',
                's.slip_number', 's.status as slip_status', 's.receipt_number', 's.rejection_reason']);
    }

    public function render()
    {
        $c = $this->caseId ? ImpoundCase::with(['sale', 'auctionEntry.auctionGroup'])->find($this->caseId) : null;
        if (! $c) {
            $s = $this->search;

            return view('livewire.fourriere.fiche-choose', [
                'cases' => ImpoundCase::when($s, fn ($q) => $q->where(fn ($w) => $w->where('registration', 'like', "%{$s}%")
                    ->orWhere('number', 'like', "%{$s}%")->orWhere('deposit_number', 'like', "%{$s}%")->orWhere('owner_name', 'like', "%{$s}%")))
                    ->orderByDesc('impounded_on')->limit(100)->get(),
            ]);
        }

        return view('livewire.fourriere.fiche', [
            'case' => $c, 'info' => ImpoundCalculator::enrich($c), 'payments' => self::payments($c),
            'canRelease' => $c->status !== 'sortie' && ImpoundCalculator::hasValidatedPayment($c),
        ]);
    }
}
