<?php

namespace App\Livewire\Aide;

use App\Livewire\AppPage;
use App\Models\FeedbackReport;
use App\Models\User;
use App\Notifications\AlertNotification;
use App\Support\Navigation;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

/**
 * Signaler un problème — Flask /api/systeme/feedback. Le signalement reste dans l'application (aucun envoi externe) :
 * les administrateurs sont prévenus par la cloche et le traitent ici.
 */
#[Title('Signaler un problème')]
class Signaler extends AppPage
{
    public const ROUTE = 'parametres.signaler';

    #[Url(as: 'page')]
    public string $pageUrl = '';

    public string $type = 'probleme';

    public string $module = '';

    public string $message = '';

    public ?int $rating = null;

    public ?int $answerId = null;

    public string $answer = '';

    public string $answerStatus = 'traite';

    public function send(): void
    {
        $data = $this->validate(['type' => ['required', 'in:'.implode(',', array_keys(FeedbackReport::TYPES))], 'module' => ['nullable', 'string', 'max:60'],
            'message' => ['required', 'string', 'min:10', 'max:4000'], 'rating' => ['nullable', 'integer', 'between:1,5'], 'pageUrl' => ['nullable', 'string', 'max:255']],
            ['message.min' => 'Décrivez le problème en quelques mots (10 caractères au moins).'], ['message' => 'message']);
        $r = FeedbackReport::create(['user_id' => auth()->id(), 'type' => $data['type'], 'module' => $data['module'] ?: null, 'message' => $data['message'],
            'rating' => $data['rating'], 'page_url' => $data['pageUrl'] ?: null]);
        foreach (User::where('is_active', true)->get()->filter(fn ($u) => $u->can('config.gerer') && $u->id !== auth()->id()) as $admin) {
            $admin->notify(new AlertNotification(['key' => 'signalement_'.$r->id, 'level' => 'warning', 'permission' => 'config.gerer', 'url' => route('parametres.signaler'),
                'title' => FeedbackReport::TYPES[$r->type].' signalé par '.auth()->user()->name, 'message' => mb_strimwidth($r->message, 0, 140, '…')]));
        }
        activity('support')->causedBy(auth()->user())->performedOn($r)->log('Signalement envoyé');
        $this->reset('message', 'rating', 'module');
        $this->notify('Merci ! Votre message a été transmis à l’administrateur.');
    }

    public function openAnswer(int $id): void
    {
        $this->authorizeWrite('config.gerer');
        $r = FeedbackReport::findOrFail($id);
        $this->answerId = $id;
        $this->answer = (string) $r->answer;
        $this->answerStatus = $r->status === 'nouveau' ? 'traite' : $r->status;
    }

    public function saveAnswer(): void
    {
        $this->authorizeWrite('config.gerer');
        $this->validate(['answer' => ['nullable', 'string', 'max:4000'], 'answerStatus' => ['required', 'in:'.implode(',', array_keys(FeedbackReport::STATUSES))]]);
        $r = FeedbackReport::findOrFail($this->answerId);
        $r->update(['answer' => $this->answer ?: null, 'status' => $this->answerStatus, 'handled_by' => auth()->id(), 'handled_at' => now()]);
        if ($r->user && $r->user_id !== auth()->id()) {
            $r->user->notify(new AlertNotification(['key' => 'reponse_'.$r->id, 'level' => 'info', 'permission' => 'dashboard.voir', 'url' => route('parametres.signaler'),
                'title' => 'Réponse à votre signalement', 'message' => mb_strimwidth($this->answer ?: FeedbackReport::STATUSES[$this->answerStatus], 0, 140, '…')]));
        }
        $this->answerId = null;
        $this->notify('Réponse enregistrée.');
    }

    public function render(Navigation $nav)
    {
        $admin = $this->allowed('config.gerer');

        return view('livewire.aide.signaler', [
            'spaces' => collect(config('navigation'))->map(fn ($s) => $s['title'])->all(),
            'reports' => FeedbackReport::with(['user', 'handler'])->when(! $admin, fn ($q) => $q->where('user_id', auth()->id()))->latest()->limit(50)->get(),
            'admin' => $admin,
        ]);
    }
}
