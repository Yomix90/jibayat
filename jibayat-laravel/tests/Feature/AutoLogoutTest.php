<?php

use App\Http\Middleware\AutoLogout;

test('a session idle longer than the configured delay is closed', function () {
    config(['jibayat.auto_logout_minutes' => 10]);
    $user = userWithRole('regisseur');

    $this->actingAs($user)
        ->withSession([AutoLogout::SESSION_KEY => time() - 11 * 60])
        ->get('/')
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('an active session stays open', function () {
    config(['jibayat.auto_logout_minutes' => 10]);
    $user = userWithRole('regisseur');

    $this->actingAs($user)
        ->withSession([AutoLogout::SESSION_KEY => time() - 9 * 60])
        ->get('/')
        ->assertOk();

    $this->assertAuthenticatedAs($user);
});

test('the status ping reports the remaining time without extending the session', function () {
    config(['jibayat.auto_logout_minutes' => 10]);
    $user = userWithRole('regisseur');
    $last = time() - 4 * 60;

    $response = $this->actingAs($user)
        ->withSession([AutoLogout::SESSION_KEY => $last])
        ->getJson(route('session.status'))
        ->assertOk();

    expect($response->json('remaining'))->toBeBetween(355, 360);
    expect(session(AutoLogout::SESSION_KEY))->toBe($last);
});

test('the header announces the auto-logout delay', function () {
    config(['jibayat.auto_logout_minutes' => 10]);

    $this->actingAs(userWithRole('regisseur'))->get('/')
        ->assertSee('Fermeture après 10 min sans activité');
});

test('a disabled account is signed out on its next request', function () {
    $user = userWithRole('collecteur');
    $user->update(['is_active' => false]);

    $this->actingAs($user)->get('/')->assertRedirect(route('login'));
    $this->assertGuest();
});
