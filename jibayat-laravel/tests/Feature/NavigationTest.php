<?php

use App\Models\User;
use App\Support\Navigation;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;

/*
| Le registre config/navigation.php pilote l'accueil, la recherche et le menu :
| il doit rester cohérent (routes, icônes, permissions) à chaque modification.
*/

test('every navigation page has a registered route, a known icon and an existing permission', function () {
    (new RolesAndPermissionsSeeder)->run();
    $permissions = Permission::pluck('name')->all();
    $iconDir = base_path('vendor/mallardduck/blade-lucide-icons/resources/svg/icons');

    $pages = app(Navigation::class)->pages();
    expect($pages)->not->toBeEmpty();

    foreach ($pages as $name => $page) {
        expect(Route::has($name))->toBeTrue("route manquante : $name");
        expect(file_exists("$iconDir/{$page['icon']}.svg"))->toBeTrue("icône inconnue : {$page['icon']} ($name)");
        if ($page['permission']) {
            expect($permissions)->toContain($page['permission']);
        }
    }
});

test('every space icon exists and space page counts match the registry', function () {
    $iconDir = base_path('vendor/mallardduck/blade-lucide-icons/resources/svg/icons');
    $nav = app(Navigation::class);

    foreach ($nav->all() as $space) {
        expect(file_exists("$iconDir/{$space['icon']}.svg"))->toBeTrue();
        $count = collect($space['sections'])->sum(fn ($s) => count($s['pages']));
        expect($space['page_count'])->toBe($count);
    }
    expect($nav->pages()->count())->toBe($nav->all()->sum('page_count'));
});

test('out-of-scope modules are not in the navigation', function () {
    $text = json_encode(config('navigation'), JSON_UNESCAPED_UNICODE);

    // Procès-verbaux et scanner sont entrés dans le périmètre le 20/11/2026 (espace « Régie des recettes »).
    expect(mb_strtolower($text))
        ->not->toContain('abattoir')
        ->not->toContain('whatsapp');
});

test('an administrator can open the home page, every space and every page', function () {
    $admin = userWithRole('administrateur');
    $nav = app(Navigation::class);

    $this->actingAs($admin)->get('/')->assertOk()->assertSee('Tableau de bord')->assertSee('Situation par module')->assertDontSee('space-card');

    foreach ($nav->all() as $key => $space) {
        $this->actingAs($admin)->get(route('espace', $key))->assertRedirect(route($nav->entryRoute($admin, $key)));
    }
    foreach ($nav->pages() as $name => $page) {
        $this->actingAs($admin)->get(route($name))->assertOk()->assertSee($page['title']);
    }
});

test('guests are redirected to the login page', function () {
    $this->get('/')->assertRedirect(route('login'));
    $this->get(route('tnb.encaisser'))->assertRedirect(route('login'));
});

test('a consultation account sees business spaces, only the help in settings, and cannot encaisser', function () {
    $user = userWithRole('consultation');
    $spaces = app(Navigation::class)->forUser($user);

    expect($spaces->keys())->toContain('tnb', 'locations', 'documents')
        ->and(array_keys($spaces['parametres']['sections']))->toBe(['aide'])
        ->and(collect($spaces['tnb']['sections'])->flatMap(fn ($s) => array_keys($s['pages'])))->not->toContain('tnb.encaisser');

    $this->actingAs($user)->get(route('tnb.terrains'))->assertOk();
    $this->actingAs($user)->get(route('tnb.encaisser'))->assertForbidden();
    $this->actingAs($user)->get(route('caisse.validation-groupee'))->assertForbidden();
    $this->actingAs($user)->get(route('parametres.utilisateurs'))->assertForbidden();
    $this->actingAs($user)->get(route('espace', 'parametres'))->assertRedirect(route('parametres.guide'));
    $this->actingAs($user)->get(route('parametres.guide'))->assertSee('Guide')->assertDontSee('Rôles & permissions');
});

test('the régisseur can encaisser and validate payments', function () {
    $user = userWithRole('regisseur');

    $this->actingAs($user)->get(route('tnb.encaisser'))->assertOk();
    $this->actingAs($user)->get(route('caisse.validation-groupee'))->assertOk();
    $this->actingAs($user)->get(route('parametres.rubriques'))->assertForbidden();
});

test('the régisseur suppléant has the same rights as the régisseur', function () {
    $regisseur = userWithRole('regisseur');
    $suppleant = userWithRole('regisseur_suppleant');

    expect($suppleant->getAllPermissions()->pluck('name')->sort()->values()->all())
        ->toBe($regisseur->getAllPermissions()->pluck('name')->sort()->values()->all());

    $this->actingAs($suppleant)->get('/')->assertOk()->assertSee('Régisseur suppléant');
});

test('help pages without permission are visible to every signed-in user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('parametres.guide'))->assertOk();
    $this->actingAs($user)->get(route('parametres.signaler'))->assertOk();
});

test('unknown spaces return 404', function () {
    $this->actingAs(userWithRole('administrateur'))->get('/abattoir')->assertNotFound();
    $this->actingAs(userWithRole('administrateur'))->get('/espaces/abattoir')->assertRedirect('/abattoir');
});

test('a module opens on its dashboard, old /espaces/ addresses redirect, the sidebar has three groups', function () {
    $admin = userWithRole('administrateur');
    $this->actingAs($admin)->get('/tnb')->assertRedirect(route('tnb.tableau-de-bord'));
    $this->actingAs($admin)->get('/espaces/tnb')->assertRedirect('/tnb')->assertStatus(301);
    $this->actingAs($admin)->get('/caisse')->assertRedirect();

    $html = $this->actingAs($admin)->get(route('tnb.terrains'))->assertOk()->getContent();
    $nav = substr($html, strpos($html, 'Navigation principale'), 60000);
    expect($nav)->toContain('Modules fiscaux')->toContain('Régie &amp; documents')->toContain('Configuration')
        ->and(strpos($nav, 'Modules fiscaux'))->toBeLessThan(strpos($nav, 'Régie &amp; documents'))
        ->and($nav)->toContain('href="'.route('tnb.tableau-de-bord').'"')
        ->and(substr_count($nav, 'href="'.route('tnb.tableau-de-bord').'"'))->toBe(1)
        ->and($nav)->not->toContain('/espaces/');
});
