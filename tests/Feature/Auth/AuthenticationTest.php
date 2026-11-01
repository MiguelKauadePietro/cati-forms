<?php

use App\Models\User;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'username' => $user->username,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('username login does not require a second factor', function () {
    $user = User::factory()->create();
    $this->post(route('login.store'), ['username' => $user->username, 'password' => 'password'])
        ->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($user);
    $this->assertFalse(session()->has('login.id'));
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'username' => $user->username,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));

    $this->assertGuest();
});

test('users are rate limited', function () {
    $user = User::factory()->create();

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post(route('login.store'), [
            'username' => $user->username,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('username');
    }

    $response = $this->post(route('login.store'), [
        'username' => $user->username,
        'password' => 'wrong-password',
    ]);

    $response->assertTooManyRequests();
});

test('login normalizes username and needs no email verification', function () {
    $user = User::factory()->create(['username' => 'cati_user']);
    $this->post(route('login.store'), ['username' => 'CATI_USER', 'password' => 'password'])
        ->assertSessionHasNoErrors()->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($user);
    $this->get('/dashboard')->assertOk();
});

test('login requires username and does not accept email instead', function () {
    $user = User::factory()->create();
    $this->post(route('login.store'), ['email' => 'legacy@example.com', 'password' => 'password'])
        ->assertSessionHasErrors('username');
    $this->assertGuest();
});

test('login renews the session identifier', function () {
    $user = User::factory()->create();
    $this->withSession(['session_probe' => 'kept'])->get(route('login'));
    $oldId = session()->getId();
    $this->post(route('login.store'), ['username' => $user->username, 'password' => 'password'])
        ->assertSessionHasNoErrors();
    expect(session()->getId())->not->toBe($oldId);
    $this->assertAuthenticatedAs($user);
});

test('logout invalidates session and renews csrf token', function () {
    $this->actingAs(User::factory()->create())->withSession(['private_marker' => 'secret'])
        ->get('/dashboard');
    $oldId = session()->getId();
    $oldToken = session()->token();
    $this->post(route('logout'))->assertRedirect('/')
        ->assertSessionMissing('private_marker');
    expect(session()->getId())->not->toBe($oldId);
    expect(session()->token())->not->toBe($oldToken);
    $this->assertGuest();
    $this->get('/dashboard')->assertRedirect(route('login'));
});

test('unknown username receives a generic credentials error', function () {
    $this->post(route('login.store'), ['username' => 'unknown', 'password' => 'password'])
        ->assertSessionHasErrors('username');
    $this->assertGuest();
});
