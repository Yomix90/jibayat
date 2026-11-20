<?php

namespace App\Livewire\Caisse;

use App\Domain\Caisse\Models\ReminderLetter;
use App\Domain\Caisse\Services\NoticeService;
use App\Livewire\AppPage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/** Lettres de relance — Flask /avis/lettre : création (tous les avis émis d'une taxe), impression, envoi, annulation motivée. */
#[Title('Lettres de relance')]
class Lettres extends AppPage
{
    use WithPagination;

    public const ROUTE = 'caisse.lettres';

    #[Url(as: 'lettre')]
    public ?int $letterId = null;

    public string $module = '';

    public string $letterType = 'relance';

    public ?int $cancelId = null;

    public string $cancelReason = '';

    public function create(NoticeService $service): void
    {
        $this->authorizeWrite('bulletins.creer');
        try {
            $l = $service->createLetter($this->module ?: null, $this->letterType, [], auth()->user());
        } catch (ValidationException $e) {
            $this->notify($e->validator->errors()->first(), 'warning');

            return;
        }
        $this->letterId = $l->id;
        $this->notify("Lettre {$l->number} créée en brouillon ({$l->taxpayer_count} avis).");
    }

    public function approve(int $id, NoticeService $service): void
    {
        $this->authorizeWrite('bulletins.creer');
        try {
            $service->approve(ReminderLetter::findOrFail($id), auth()->user());
        } catch (ValidationException $e) {
            $this->notify($e->validator->errors()->first(), 'warning');

            return;
        }
        $this->notify('Lettre marquée comme envoyée.');
    }

    public function askCancel(int $id): void
    {
        $this->authorizeWrite('bulletins.creer');
        $this->cancelId = $id;
        $this->cancelReason = '';
        $this->resetValidation();
    }

    public function confirmCancel(NoticeService $service): void
    {
        $this->authorizeWrite('bulletins.creer');
        $this->validate(['cancelReason' => ['required', 'string', 'min:5', 'max:255']], [], ['cancelReason' => 'motif']);
        $l = ReminderLetter::findOrFail($this->cancelId);
        if ($l->isCancelled()) {
            $this->cancelId = null;

            return;
        }
        $service->cancelLetter($l, auth()->user(), $this->cancelReason);
        $this->cancelId = null;
        $this->notify("Lettre {$l->number} annulée ; ses avis peuvent être repris dans une autre lettre.", 'warning');
    }

    public function render(NoticeService $service)
    {
        $current = $this->letterId ? ReminderLetter::find($this->letterId) : null;

        return view('livewire.caisse.lettres', [
            'letters' => ReminderLetter::with('agent')->orderByDesc('id')->paginate(20),
            'current' => $current, 'lines' => $current ? $service->letterLines($current) : collect(),
        ]);
    }
}
