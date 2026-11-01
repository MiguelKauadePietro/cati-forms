<?php

use Inertia\Testing\AssertableInertia as Assert;

test('appearance cookie is validated before sharing with Inertia', function (string $cookie, string $expected) {
    $this->withUnencryptedCookie('appearance', $cookie)
        ->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('appearance', $expected));
})->with([
    'light' => ['light', 'light'],
    'dark' => ['dark', 'dark'],
    'system' => ['system', 'system'],
    'invalid' => ['invalid', 'system'],
]);
