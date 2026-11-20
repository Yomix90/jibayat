<?php

use App\Livewire\Aide;
use App\Models\FeedbackReport;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

test('guide and about pages open for every role; the about page shows the changelog and checks for updates', function () {
    $this->actingAs(userWithRole('collecteur'));
    $this->get(route('parametres.guide'))->assertOk()->assertSee('Démarches pas à pas')->assertSee('Plan de l’application');
    $this->get(route('parametres.a-propos'))->assertOk()->assertSee('Quoi de neuf ?')->assertSee('Pièces officielles immuables');

    Livewire::test(Aide\APropos::class)->call('check')->assertSee('Vérification non configurée');
    config(['jibayat.update_url' => 'https://updates.example.test/latest', 'jibayat.version' => '4.0.0-dev']);
    Http::fake(['updates.example.test/*' => Http::response(['tag_name' => 'v4.1.0', 'name' => 'Jibayat 4.1', 'body' => 'Corrections'])]);
    Livewire::test(Aide\APropos::class)->call('check')->assertSee('Nouvelle version disponible : Jibayat 4.1');
});

test('a user reports a problem; administrators are notified and answer; the user sees the answer', function () {
    $admin = userWithRole('administrateur');
    $user = userWithRole('collecteur');
    $this->actingAs($user);
    Livewire::test(Aide\Signaler::class)->set('message', 'court')->call('send')->assertHasErrors('message')
        ->set('message', 'L’impression de la quittance est coupée en bas.')->set('module', 'TNB')->call('send')->assertHasNoErrors();
    $r = FeedbackReport::firstOrFail();
    expect($r->user_id)->toBe($user->id)->and($admin->fresh()->notifications)->toHaveCount(1);

    Livewire::test(Aide\Signaler::class)->call('openAnswer', $r->id)->assertForbidden();
    $this->actingAs($admin);
    Livewire::test(Aide\Signaler::class)->assertSee('L’impression de la quittance')->call('openAnswer', $r->id)->set('answer', 'Corrigé, merci.')->call('saveAnswer');
    expect($r->fresh()->status)->toBe('traite')->and($user->fresh()->notifications)->toHaveCount(1);
    $this->actingAs($user);
    Livewire::test(Aide\Signaler::class)->assertSee('Corrigé, merci.');
});
