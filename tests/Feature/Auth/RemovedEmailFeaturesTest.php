<?php

use Illuminate\Support\Facades\Route;

test('email recovery and verification routes are not registered', function () {
    foreach (['password.request', 'password.email', 'password.reset', 'password.store', 'verification.notice', 'verification.verify', 'verification.send'] as $name) {
        expect(Route::has($name))->toBeFalse();
    }
    foreach (['/forgot-password', '/reset-password/token', '/verify-email'] as $url) {
        $this->get($url)->assertNotFound();
    }
    $this->post('/forgot-password')->assertNotFound();
    $this->post('/reset-password')->assertNotFound();
    $this->post('/email/verification-notification')->assertNotFound();
});
