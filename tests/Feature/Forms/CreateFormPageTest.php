<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('guests cannot access the form creation page', function () {
    $this->get('/forms/create')->assertRedirect('/login');
});

test('authenticated users receive the form creation page through Inertia', function () {
    $this->actingAs(User::factory()->create())->get('/forms/create')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('forms/Create'));
});
