<?php

namespace App\Livewire\Parametres;

use App\Livewire\AppPage;

/**
 * Base des écrans de l'espace Paramètres : toute écriture exige « config.gerer » (Flask : peut_config).
 */
abstract class SettingsPage extends AppPage
{
    protected function authorizeWrite(string $ability = 'config.gerer'): void
    {
        parent::authorizeWrite($ability);
    }

    public function canWrite(string $ability = 'config.gerer'): bool
    {
        return $this->allowed($ability);
    }
}
