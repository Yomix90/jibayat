<?php

use App\Models\User;

test('login screen can be rendered', function () {
    $this->get('/login')->assertOk()->assertSee('Identifiant');
});

test('users can authenticate with their username', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', ['login' => $user->username, 'password' => 'password']);

    $this->assertAuthenticated();
    $response->assertRedirect(route('home', absolute: false));
    expect($user->fresh()->last_login_at)->not->toBeNull();
});

test('users can authenticate with their email', function () {
    $user = User::factory()->create();

    $this->post('/login', ['login' => $user->email, 'password' => 'password']);

    $this->assertAuthenticated();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', ['login' => $user->username, 'password' => 'wrong-password']);

    $this->assertGuest();
});

test('inactive users can not authenticate', function () {
    $user = User::factory()->create(['is_active' => false]);

    $this->post('/login', ['login' => $user->username, 'password' => 'password']);

    $this->assertGuest();
});

test('public registration is disabled', function () {
    $this->get('/register')->assertNotFound();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect(route('login'));
});
