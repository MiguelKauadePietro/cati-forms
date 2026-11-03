<?php

use App\Models\User;

test('my forms requires a session and returns JSON for guests', function () {
    $this->get('/api/v1/forms/mine')->assertUnauthorized()
        ->assertJsonStructure(['message'])->assertHeaderMissing('Location');
});

test('my forms is empty when only another user has forms', function () {
    User::factory()->create()->forms()->create(['title' => 'Outro', 'description' => 'Descrição']);

    $this->actingAs(User::factory()->create())->getJson('/api/v1/forms/mine')
        ->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.total', 0);
});

test('my forms ignores supplied authors and sorts newest first with stable ties', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $other->forms()->create(['title' => 'Outro', 'description' => 'Privado do dashboard']);
    $old = $user->forms()->create(['title' => 'Antigo', 'description' => 'Descrição']);
    $old->forceFill(['created_at' => now()->subDay()])->save();
    $new = $user->forms()->create(['title' => 'Novo', 'description' => 'Descrição']);
    $last = $user->forms()->create(['title' => 'Último', 'description' => 'Descrição']);
    $last->forceFill(['created_at' => $new->created_at])->save();
    $new->questions()->create(['type' => 'discursive', 'prompt' => 'Pergunta', 'position' => 1]);

    $response = $this->actingAs($user)->getJson('/api/v1/forms/mine?creator_id='.$other->id.'&user_id='.$other->id)
        ->assertOk()->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.id', $last->id)->assertJsonPath('data.1.id', $new->id)
        ->assertJsonPath('data.2.id', $old->id)
        ->assertJsonPath('data.0.creator_id', $user->id)
        ->assertJsonPath('data.0.created_at', $last->created_at->toISOString())
        ->assertJsonPath('data.0.title', 'Último')->assertJsonPath('data.0.description', 'Descrição');

    expect($response->json('data.1'))->not->toHaveKey('questions');
    expect($response->json('data.0'))->not->toHaveKey('creator');
});

test('my forms paginates twelve records without losing older forms', function () {
    $user = User::factory()->create();
    foreach (range(1, 13) as $index) {
        $user->forms()->create(['title' => 'Formulário '.$index, 'description' => 'Descrição']);
    }
    $this->actingAs($user)->getJson('/api/v1/forms/mine')
        ->assertOk()->assertJsonCount(12, 'data')->assertJsonPath('meta.total', 13)
        ->assertJsonPath('meta.per_page', 12)->assertJsonPath('meta.last_page', 2)
        ->assertJsonStructure(['links' => ['first', 'last', 'prev', 'next']]);
    $this->getJson('/api/v1/forms/mine?page=2')->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.current_page', 2);
});

test('my forms rejects invalid page numbers', function (string $page) {
    $this->actingAs(User::factory()->create())->getJson('/api/v1/forms/mine?page='.$page)
        ->assertUnprocessable()->assertJsonValidationErrors('page');
})->with(['0', '-1', 'abc', '1.5']);
