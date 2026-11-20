<?php

use App\Livewire\Passkeys;
use App\Models\User;
use Laragear\WebAuthn\Models\WebAuthnCredential;
use Livewire\Livewire;

test('a signed-in user gets registration options; a guest does not', function () {
    $this->postJson('/webauthn/register/options')->assertStatus(403);
    $user = userWithRole('regisseur');
    $this->actingAs($user)->postJson('/webauthn/register/options')->assertOk()
        ->assertJsonPath('rp.name', config('app.name'))->assertJsonStructure(['challenge', 'user' => ['id', 'name', 'displayName'], 'authenticatorSelection']);
});

test('a guest gets login options (with or without username), password login still works', function () {
    $user = userWithRole('regisseur');
    $this->postJson('/webauthn/login/options')->assertOk()->assertJsonStructure(['challenge']);
    $this->postJson('/webauthn/login/options', ['username' => $user->username])->assertOk();
    $this->post('/login', ['login' => $user->username, 'password' => 'password'])->assertRedirect();
    $this->assertAuthenticatedAs($user);
});

test('the profile lists and removes registered devices; the header shows the fingerprint state', function () {
    $user = userWithRole('regisseur');
    WebAuthnCredential::forceCreate(['id' => 'cred-1', 'authenticatable_type' => User::class, 'authenticatable_id' => $user->id, 'user_id' => 'u-1',
        'alias' => 'PC de la régie', 'counter' => 0, 'rp_id' => 'localhost', 'origin' => 'http://localhost', 'aaguid' => null,
        'public_key' => 'cGs=', 'attestation_format' => 'none']);
    $this->actingAs($user)->get(route('profile.edit'))->assertOk()->assertSee('PC de la régie');
    $this->get(route('home'))->assertSee('Empreinte activée');
    Livewire::test(Passkeys::class)->call('remove', 'cred-1');
    expect($user->webAuthnCredentials()->count())->toBe(0);
});
