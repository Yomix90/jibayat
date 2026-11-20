<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Base des écrans plein-page : mise en page, en-tête du registre (constante ROUTE), messages, droits.
 * La consultation est protégée par la permission de la route (config/navigation.php) ;
 * chaque action d'écriture vérifie sa propre permission côté serveur.
 */
#[Layout('layouts.app')]
abstract class AppPage extends Component
{
    /** Nom de route du registre de navigation (en-tête, fil d'Ariane). */
    public function pageRoute(): string
    {
        return static::ROUTE;
    }

    protected function notify(string $message, string $type = 'success'): void
    {
        $this->dispatch('notify', message: __($message), type: $type);
    }

    protected function authorizeWrite(string $ability): void
    {
        Gate::authorize($ability);
    }

    public function allowed(string $ability): bool
    {
        return Gate::allows($ability);
    }
}
