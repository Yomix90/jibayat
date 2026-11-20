<?php

use App\Livewire\PageSearch;
use App\Models\RecentPage;
use App\Support\Navigation;
use Livewire\Livewire;

test('search matches titles, subtitles and keywords, ignoring accents and case', function () {
    $admin = userWithRole('administrateur');
    $nav = app(Navigation::class);

    expect($nav->search($admin, 'souk')->pluck('route'))->toContain('souk.contrats');
    expect($nav->search($admin, 'ARRETE')->pluck('route'))->toContain('parametres.arretes');   // « Arrêtés »
    expect($nav->search($admin, 'immatriculation')->pluck('route'))->toContain('transport.vehicules'); // mot-clé
    expect($nav->search($admin, 'أداء')->pluck('route'))->toContain('tnb.encaisser');          // sous-titre arabe
    expect($nav->search($admin, 'zzzz'))->toBeEmpty();
});

test('search only returns pages the user may open', function () {
    $user = userWithRole('consultation');

    $routes = app(Navigation::class)->search($user, 'encaisser')->pluck('route');

    expect($routes)->not->toContain('tnb.encaisser');
});

test('the Livewire search box lists matching pages', function () {
    $this->actingAs(userWithRole('administrateur'));

    Livewire::test(PageSearch::class)
        ->set('query', 'carnets')
        ->assertSee('Carnets')
        ->set('query', 'introuvable-xyz')
        ->assertSee('Aucun résultat');
});

test('visiting a page records it in the user history', function () {
    $admin = userWithRole('administrateur');

    $this->actingAs($admin)->get(route('locations.locataires'))->assertOk();
    $this->actingAs($admin)->get(route('locations.locataires'))->assertOk();
    $this->actingAs($admin)->get(route('tnb.recensement'))->assertOk();

    expect(RecentPage::where('user_id', $admin->id)->count())->toBe(2)
        ->and(RecentPage::where('route_name', 'locations.locataires')->value('visits'))->toBe(2);
});

test('the search box is available in the top bar of every page', function () {
    $this->actingAs(userWithRole('administrateur'))
        ->get(route('tnb.terrains'))
        ->assertOk()
        ->assertSee('Chercher : page, contribuable');
});
