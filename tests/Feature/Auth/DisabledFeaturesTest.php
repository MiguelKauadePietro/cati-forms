<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

test('second factor and passkey endpoints remain disabled', function () {
    $user = User::factory()->create();
    foreach (['two-factor.login', 'two-factor.enable', 'two-factor.confirm', 'two-factor.disable', 'two-factor.qr-code', 'two-factor.secret-key', 'two-factor.recovery-codes', 'passkey.login', 'passkeys.index', 'passkeys.store', 'well-known.passkeys'] as $name) {
        expect(Route::has($name))->toBeFalse();
    }
    foreach (['/two-factor-challenge', '/.well-known/passkey-endpoints'] as $url) {
        $this->get($url)->assertNotFound();
    }
    $this->actingAs($user);
    foreach (['/user/two-factor-authentication', '/user/confirmed-two-factor-authentication', '/passkeys/login', '/user/passkeys'] as $url) {
        $this->post($url)->assertNotFound();
    }
});

test('private pages require authentication', function (string $url) {
    $this->get($url)->assertRedirect(route('login'));
})->with(['/dashboard', '/settings/profile', '/settings/security', '/settings/appearance']);
