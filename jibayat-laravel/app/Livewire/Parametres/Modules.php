<?php

namespace App\Livewire\Parametres;

use App\Models\AppModule;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Title;

/**
 * Modules actifs — Flask : /api/systeme/modules. Un module désactivé disparaît de l'accueil,
 * de la recherche et du menu, et ses pages ne s'ouvrent plus (les données sont conservées).
 */
#[Title('Modules actifs')]
class Modules extends SettingsPage
{
    public const ROUTE = 'parametres.modules';

    public function toggle(int $id): void
    {
        $this->authorizeWrite();
        $m = AppModule::findOrFail($id);
        $m->update(['is_active' => ! $m->is_active]);
        Cache::forget('jibayat.inactive_modules');
        $this->notify(($m->label ?: $m->code).($m->is_active ? ' activé.' : ' désactivé.'));
    }

    public function render()
    {
        $spaces = array_flip(config('jibayat.space_modules'));
        $pages = collect(config('jibayat.page_modules'))->groupBy(fn ($module) => $module, preserveKeys: true)
            ->map(fn ($routes) => $routes->keys()->count());

        return view('livewire.parametres.modules', [
            'modules' => AppModule::orderBy('sort_order')->get(),
            'spaces' => $spaces,
            'pages' => $pages,
        ]);
    }
}
