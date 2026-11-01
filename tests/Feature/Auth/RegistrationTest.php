<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'username' => 'Test_User',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $user = User::sole();
    expect($user->username)->toBe('test_user');
    expect(Hash::check('password', $user->password))->toBeTrue();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('registration rejects missing and duplicate usernames', function () {
    User::factory()->create(['username' => 'taken']);
    foreach (['', 'TAKEN'] as $username) {
        $this->post(route('register.store'), [
            'username' => $username,
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('username');
        $this->assertGuest();
    }
    expect(User::count())->toBe(1);
});

test('registration validates passwords on the server', function (mixed $password, mixed $confirmation) {
    $this->post(route('register.store'), [
        'username' => 'new_user', 'password' => $password, 'password_confirmation' => $confirmation,
    ])->assertSessionHasErrors('password');
    $this->assertGuest();
    expect(User::count())->toBe(0);
})->with([
    'empty' => ['', ''],
    'too short' => ['short', 'short'],
    'mismatch' => ['password', 'different'],
    'missing confirmation' => ['password', null],
    'invalid type' => [['password'], ['password']],
]);
