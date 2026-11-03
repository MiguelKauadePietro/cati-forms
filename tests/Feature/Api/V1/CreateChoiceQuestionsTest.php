<?php

use App\Models\Form;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\User;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->withoutVite();
});

function choiceFormPayload(array $overrides = []): array
{
    return ['title' => 'Áreas de interesse', 'description' => 'Escolha suas áreas.', 'questions' => [
        array_replace(['type' => 'multiple_choice', 'prompt' => 'Quais áreas?', 'options' => [['label' => 'Design'], ['label' => 'Desenvolvimento']]], $overrides),
    ]];
}

test('RF05 saves ordered options with correct relationships and mixed discursive questions', function () {
    $payload = choiceFormPayload();
    $payload['questions'][] = ['type' => 'discursive', 'prompt' => 'Conte sua experiência.'];
    $response = $this->actingAs(User::factory()->create())->postJson('/api/v1/forms', $payload)
        ->assertCreated()->assertJsonCount(2, 'data.questions.0.options')
        ->assertJsonPath('data.questions.0.options.0.label', 'Design')
        ->assertJsonPath('data.questions.0.options.1.position', 2)
        ->assertJsonPath('data.questions.1.options', []);
    $question = Question::where('type', 'multiple_choice')->sole();
    expect($question->options->pluck('position')->all())->toBe([1, 2]);
    foreach ($question->options as $option) {
        expect($option->question->is($question))->toBeTrue();
    }
    $response->assertJsonPath('data.questions.0.options.0.id', $question->options->first()->id);
});

test('choice questions accept one option and repeated labels without imposing an option count cap', function () {
    $user = User::factory()->create();
    foreach ([1, 25] as $count) {
        $this->actingAs($user)->postJson('/api/v1/forms', choiceFormPayload([
            'options' => array_fill(0, $count, ['label' => 'Mesmo texto']),
        ]))->assertCreated()->assertJsonCount($count, 'data.questions.0.options');
    }
    $this->assertDatabaseCount('question_options', 26);
});

test('invalid objective definitions reject the entire form', function (array $overrides, string $key) {
    $this->actingAs(User::factory()->create())->postJson('/api/v1/forms', choiceFormPayload($overrides))
        ->assertUnprocessable()->assertJsonValidationErrors($key);
    $this->assertDatabaseCount('forms', 0);
    $this->assertDatabaseCount('questions', 0);
    $this->assertDatabaseCount('question_options', 0);
})->with([
    'null options' => [['options' => null], 'questions.0.options'],
    'empty options' => [['options' => []], 'questions.0.options'],
    'non-list options' => [['options' => ['named' => ['label' => 'Texto']]], 'questions.0.options'],
    'scalar options' => [['options' => 'Texto'], 'questions.0.options'],
    'invalid option' => [['options' => [null]], 'questions.0.options.0'],
    'missing label' => [['options' => [[]]], 'questions.0.options.0.label'],
    'blank label' => [['options' => [['label' => '   ']]], 'questions.0.options.0.label'],
    'invalid label' => [['options' => [['label' => ['Texto']]]], 'questions.0.options.0.label'],
    'long label' => [['options' => [['label' => str_repeat('á', 10001)]]], 'questions.0.options.0.label'],
    'forged option id' => [['options' => [['label' => 'Texto', 'id' => 999]]], 'questions.0.options.0'],
    'forged option position' => [['options' => [['label' => 'Texto', 'position' => 999]]], 'questions.0.options.0'],
    'forged option question' => [['options' => [['label' => 'Texto', 'question_id' => 999]]], 'questions.0.options.0'],
    'discursive options' => [['type' => 'discursive'], 'questions.0.options'],
    'response field' => [['selected_options' => [1]], 'questions.0'],
    'unsupported type' => [['type' => 'unsupported'], 'questions.0.type'],
]);

test('objective question requires an options field', function () {
    $payload = choiceFormPayload();
    unset($payload['questions'][0]['options']);
    $this->actingAs(User::factory()->create())->postJson('/api/v1/forms', $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('questions.0.options');
    $this->assertDatabaseCount('forms', 0);
});

test('option label accepts the UTF8 character limit', function () {
    $label = str_repeat('ç', 10000);
    $this->actingAs(User::factory()->create())->postJson('/api/v1/forms', choiceFormPayload(['options' => [['label' => $label]]]))
        ->assertCreated()->assertJsonPath('data.questions.0.options.0.label', $label);
});

test('discursive question accepts an explicit empty options list', function () {
    $this->actingAs(User::factory()->create())->postJson('/api/v1/forms', choiceFormPayload(['type' => 'discursive', 'options' => []]))
        ->assertCreated()->assertJsonPath('data.questions.0.options', []);
});

test('option persistence failure rolls back all dependent records', function () {
    Event::listen('eloquent.creating: '.QuestionOption::class, function (QuestionOption $option) {
        if ($option->position === 2) {
            throw new RuntimeException('Controlled option failure');
        }
    });
    try {
        $this->withoutExceptionHandling()->actingAs(User::factory()->create());
        expect(fn () => $this->postJson('/api/v1/forms', choiceFormPayload()))->toThrow(RuntimeException::class, 'Controlled option failure');
        $this->assertDatabaseCount('forms', 0);
        $this->assertDatabaseCount('questions', 0);
        $this->assertDatabaseCount('question_options', 0);
    } finally {
        Event::forget('eloquent.creating: '.QuestionOption::class);
    }
});

test('options remain independent and survive deletion of a creator account', function () {
    $user = User::factory()->create();
    foreach (range(1, 2) as $unused) {
        $this->actingAs($user)->postJson('/api/v1/forms', choiceFormPayload())->assertCreated();
    }
    expect(Question::all()->map(fn ($q) => $q->options()->count())->all())->toBe([2, 2]);
    $this->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect('/');
    $this->assertDatabaseCount('question_options', 4);
    expect(Form::whereNotNull('creator_id')->exists())->toBeFalse();
});

test('RF06 combines all three types with independent ordered options', function () {
    $payload = choiceFormPayload();
    $payload['questions'][] = ['type' => 'discursive', 'prompt' => 'Conte sua experiência.'];
    $payload['questions'][] = ['type' => 'single_choice_with_justification', 'prompt' => 'Qual sua prioridade?', 'options' => [['label' => 'Design']]];
    $this->actingAs(User::factory()->create())->postJson('/api/v1/forms', $payload)->assertCreated()
        ->assertJsonCount(3, 'data.questions')
        ->assertJsonPath('data.questions.2.type', 'single_choice_with_justification')
        ->assertJsonPath('data.questions.2.position', 3)
        ->assertJsonPath('data.questions.2.options.0.position', 1)
        ->assertJsonPath('data.questions.2.options.0.label', 'Design');
    $this->assertDatabaseCount('questions', 3);
    $this->assertDatabaseCount('question_options', 3);
});

test('RF06 rejects invalid definitions without accepting participant answer fields', function (array $overrides, string $key) {
    $this->actingAs(User::factory()->create())->postJson('/api/v1/forms', choiceFormPayload([
        'type' => 'single_choice_with_justification', ...$overrides,
    ]))->assertUnprocessable()->assertJsonValidationErrors($key);
    $this->assertDatabaseCount('forms', 0);
    $this->assertDatabaseCount('question_options', 0);
})->with([
    'empty options' => [['options' => []], 'questions.0.options'],
    'null options' => [['options' => null], 'questions.0.options'],
    'blank label' => [['options' => [['label' => '   ']]], 'questions.0.options.0.label'],
    'long label' => [['options' => [['label' => str_repeat('á', 10001)]]], 'questions.0.options.0.label'],
    'justification' => [['justification' => 'Resposta antecipada'], 'questions.0'],
    'chosen option' => [['selected_option_id' => 1], 'questions.0'],
    'optional justification' => [['requires_justification' => false], 'questions.0'],
]);

test('RF06 requires options even when the field is omitted', function () {
    $payload = choiceFormPayload(['type' => 'single_choice_with_justification']);
    unset($payload['questions'][0]['options']);
    $this->actingAs(User::factory()->create())->postJson('/api/v1/forms', $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('questions.0.options');
});
