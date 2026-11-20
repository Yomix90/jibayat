<?php

namespace App\Livewire\Parametres;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;

/**
 * Sécurité des sessions — Flask : /api/systeme/session-config (auto_logout_minutes).
 */
#[Title('Sécurité des sessions')]
class Sessions extends SettingsPage
{
    public const ROUTE = 'parametres.sessions';

    public int $minutes = 10;

    public function mount(): void
    {
        $this->minutes = (int) config('jibayat.auto_logout_minutes');
    }

    public function save(): void
    {
        $this->authorizeWrite();
        $this->validate(['minutes' => ['required', 'integer', 'min:1', 'max:240']], [], ['minutes' => 'délai']);
        Setting::put('auto_logout_minutes', (string) $this->minutes);
        config(['jibayat.auto_logout_minutes' => $this->minutes]);
        $this->notify("Déconnexion automatique réglée à {$this->minutes} min.");
    }

    public function render()
    {
        return view('livewire.parametres.sessions', [
            'openSessions' => DB::table('sessions')->whereNotNull('user_id')
                ->where('last_activity', '>=', now()->subMinutes($this->minutes)->timestamp)->count(),
        ]);
    }
}
