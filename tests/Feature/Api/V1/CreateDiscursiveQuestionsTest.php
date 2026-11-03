<?php

use App\Models\Form;
use App\Models\Question;
use App\Models\User;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->withoutVite();
});

function discursivePayload(array $questions): array
{
    return ['title' => 'Título', 'description' => 'Descrição', 'questions' => $questions];
}

test('creates ordered discursive questions belonging to independent forms', function () {
    $user = User::factory()->create();
    foreach (['Primeiro', 'Segundo'] as $title) {
        $response = $this->actingAs($user)->postJson('/api/v1/forms', [
            ...discursivePayload([
                ['type' => 'discursive', 'prompt' => 'Por quê?'],
                ['type' => 'discursive', 'prompt' => 'Conte sua experiência.'],
            ]), 'title' => $title,
        ])->assertCreated()->assertJsonCount(2, 'data.questions')
            ->assertJsonPath('data.questions.0.position', 1)
            ->assertJsonPath('data.questions.1.position', 2)
            ->assertJsonPath('data.questions.0.type', 'discursive')
            ->assertJsonPath('data.questions.0.prompt', 'Por quê?');
        $form = Form::findOrFail($response->json('data.id'));
        expect($form->questions->pluck('position')->all())->toBe([1, 2]);
        foreach ($form->questions as $question) {
            expect($question->form->is($form))->toBeTrue();
        }
    }
    $this->assertDatabaseCount('forms', 2);
    $this->assertDatabaseCount('questions', 4);
});

test('empty question list remains compatible with RF03', function () {
    $this->actingAs(User::factory()->create())->postJson('/api/v1/forms', discursivePayload([]))
        ->assertCreated()->assertJsonPath('data.questions', []);
    $this->assertDatabaseCount('forms', 1);
    $this->assertDatabaseCount('questions', 0);
});

test('invalid questions reject the whole submission', function (mixed $questions, string $key) {
    $this->actingAs(User::factory()->create())->postJson('/api/v1/forms', [
        'title' => 'Título', 'description' => 'Descrição', 'questions' => $questions,
    ])->assertUnprocessable()->assertJsonValidationErrors($key);
    $this->assertDatabaseCount('forms', 0);
    $this->assertDatabaseCount('questions', 0);
})->with([
    'null' => [null, 'questions'],
    'string' => ['invalid', 'questions'],
    'object' => [['named' => ['type' => 'discursive', 'prompt' => 'Texto']], 'questions'],
    'invalid item' => [[null], 'questions.0'],
    'missing type' => [[['prompt' => 'Texto']], 'questions.0.type'],
    'invalid type' => [[['type' => ['discursive'], 'prompt' => 'Texto']], 'questions.0.type'],
    'future type' => [[['type' => 'unknown', 'prompt' => 'Texto']], 'questions.0.type'],
    'missing prompt' => [[['type' => 'discursive']], 'questions.0.prompt'],
    'blank prompt' => [[['type' => 'discursive', 'prompt' => '   ']], 'questions.0.prompt'],
    'invalid prompt' => [[['type' => 'discursive', 'prompt' => ['Texto']]], 'questions.0.prompt'],
    'long prompt' => [[['type' => 'discursive', 'prompt' => str_repeat('á', 10001)]], 'questions.0.prompt'],
    'foreign form' => [[['type' => 'discursive', 'prompt' => 'Texto', 'form_id' => 99]], 'questions.0'],
    'position' => [[['type' => 'discursive', 'prompt' => 'Texto', 'position' => 99]], 'questions.0'],
    'id' => [[['type' => 'discursive', 'prompt' => 'Texto', 'id' => 99]], 'questions.0'],
]);

test('accepts a single question at the multibyte character limit', function () {
    $prompt = str_repeat('á', 10000);
    $this->actingAs(User::factory()->create())->postJson('/api/v1/forms', discursivePayload([
        ['type' => 'discursive', 'prompt' => $prompt],
    ]))->assertCreated()->assertJsonPath('data.questions.0.prompt', $prompt);
    expect(Question::sole()->prompt)->toBe($prompt);
});

test('a persistence failure rolls back the form and previously saved questions', function () {
    Event::listen('eloquent.creating: '.Question::class, function (Question $question) {
        if ($question->position === 2) {
            throw new RuntimeException('Controlled persistence failure');
        }
    });
    try {
        $this->withoutExceptionHandling()->actingAs(User::factory()->create());
        expect(fn () => $this->postJson('/api/v1/forms', discursivePayload([
            ['type' => 'discursive', 'prompt' => 'Primeira'],
            ['type' => 'discursive', 'prompt' => 'Segunda'],
        ])))->toThrow(RuntimeException::class, 'Controlled persistence failure');
        $this->assertDatabaseCount('forms', 0);
        $this->assertDatabaseCount('questions', 0);
    } finally {
        Event::forget('eloquent.creating: '.Question::class);
    }
});

test('account deletion preserves the form and its questions', function () {
    $user = User::factory()->create();
    $response = $this->actingAs($user)->postJson('/api/v1/forms', discursivePayload([
        ['type' => 'discursive', 'prompt' => 'Preservada'],
    ]))->assertCreated();
    $this->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect('/');
    $this->assertDatabaseHas('forms', ['id' => $response->json('data.id'), 'creator_id' => null]);
    expect(Question::sole()->prompt)->toBe('Preservada');
});
