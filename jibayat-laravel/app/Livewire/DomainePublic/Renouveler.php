<?php

namespace App\Livewire\DomainePublic;

use App\Domain\DomainePublic\Models\PublicOccupation;
use App\Domain\DomainePublic\Services\OccupationService;
use App\Livewire\AppPage;
use Livewire\Attributes\Title;

/** Renouvellement des autorisations — Flask /occupation-domaine/<id>/renouveler : autorisations expirées ou bientôt échues. */
#[Title('Renouvellement — Domaine public')]
class Renouveler extends AppPage
{
    public const ROUTE = 'domaine-public.renouveler';

    public int $withinDays = 60;

    public function renew(int $id, string $duration, OccupationService $service): void
    {
        $this->authorizeWrite('domaine_public.modifier');
        abort_unless(array_key_exists($duration, OccupationService::DURATIONS), 422);
        $o = PublicOccupation::where('is_active', true)->findOrFail($id);
        $until = $service->renew($o, $duration, auth()->user());
        $this->notify("Occupation n° {$o->number} renouvelée jusqu’au ".\Illuminate\Support\Carbon::parse($until)->format('d/m/Y').'.');
    }

    public ?int $contactId = null;

    public string $contactNote = '';

    /** L'agent note qu'il a contacté le titulaire pour le renouvellement (l'alerte de la cloche s'arrête pour 15 jours). */
    public function openContact(int $id): void
    {
        $this->authorizeWrite('domaine_public.modifier');
        $this->contactId = $id;
        $this->contactNote = '';
        $this->resetValidation();
    }

    public function saveContact(): void
    {
        $this->authorizeWrite('domaine_public.modifier');
        $this->validate(['contactNote' => ['required', 'string', 'min:3', 'max:255']], [], ['contactNote' => 'résultat du contact']);
        $o = PublicOccupation::findOrFail($this->contactId);
        $o->update(['renewal_contacted_at' => now(), 'renewal_contact_note' => $this->contactNote]);
        activity('domaine_public')->causedBy(auth()->user())->performedOn($o)->withProperties(['note' => $this->contactNote])
            ->log("Titulaire contacté pour le renouvellement de l'occupation n° {$o->number}");
        $this->contactId = null;
        $this->notify(__('Contact enregistré.'));
    }

    public function render()
    {
        $limit = today()->addDays(max(0, $this->withinDays))->toDateString();

        return view('livewire.domaine-public.renouveler', [
            'rows' => PublicOccupation::with('taxpayer')->where('is_active', true)
                ->where(fn ($q) => $q->where('ends_on', '<=', $limit)->orWhereIn('status', ['expire', 'suspendu']))
                ->orderBy('ends_on')->get(),
        ]);
    }
}
