<?php

namespace App\Livewire\Parametres;

use App\Domain\Systeme\BackupService;
use Livewire\Attributes\Title;

/** Sauvegardes — Flask /parametres-systeme (sauvegarde manuelle, automatique, copie externe, journal). */
#[Title('Sauvegardes')]
class Sauvegardes extends SettingsPage
{
    public const ROUTE = 'parametres.sauvegardes';

    public array $settings = [];

    public function mount(BackupService $service): void
    {
        $this->settings = $service->settings();
    }

    public function backupNow(BackupService $service): void
    {
        $this->authorizeWrite();
        try {
            [, $messages] = $service->create('manuel', auth()->user());
        } catch (\Throwable $e) {
            $this->notify('Sauvegarde impossible : '.$e->getMessage(), 'error');

            return;
        }
        $this->notify(implode(' ', $messages));
    }

    public function saveSettings(BackupService $service): void
    {
        $this->authorizeWrite();
        $this->validate(['settings.keep' => ['required', 'integer', 'between:1,365'], 'settings.time' => ['required', 'date_format:H:i'],
            'settings.copy_dir' => ['nullable', 'string', 'max:255']], [], ['settings.keep' => 'nombre conservé', 'settings.time' => 'heure', 'settings.copy_dir' => 'dossier de copie']);
        $service->saveSettings($this->settings, auth()->user());
        $this->notify('Réglages des sauvegardes enregistrés.');
    }

    public function delete(string $name, BackupService $service): void
    {
        $this->authorizeWrite();
        $service->delete($name, auth()->user());
        $this->notify("Sauvegarde $name supprimée.", 'warning');
    }

    public function render(BackupService $service)
    {
        return view('livewire.parametres.sauvegardes', ['backups' => $service->list()]);
    }
}
