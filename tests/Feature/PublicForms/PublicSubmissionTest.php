<?php

use App\Actions\Forms\CreateForm;
use App\Models\Answer;
use App\Models\Form;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Tests\Support\EnforceRequestForgeryProtection;

function publicResponseForm(bool $requestEmail = false): Form
{
    return app(CreateForm::class)->handle(User::factory()->create(), 'Pesquisa', 'Descrição', [
        ['type' => 'discursive', 'prompt' => 'Opinião?'],
        ['type' => 'multiple_choice', 'prompt' => 'Áreas?', 'options' => [['label' => 'A'], ['label' => 'B']]],
        ['type' => 'single_choice_with_justification', 'prompt' => 'Preferida?', 'options' => [['label' => 'C'], ['label' => 'D']]],
    ], $requestEmail);
}

function publicSubmissionPayload(Form $form): array
{
    return ['answers' => [
        ['question_id' => $form->questions[0]->id, 'text' => 'Minha opinião'],
        ['question_id' => $form->questions[1]->id, 'option_ids' => $form->questions[1]->options->pluck('id')->all()],
        ['question_id' => $form->questions[2]->id, 'option_ids' => [$form->questions[2]->options[0]->id], 'justification' => 'Meu motivo'],
    ]];
}

function publicSubmissionUrl(Form $form): string
{
    return '/api/v1/public/forms/'.$form->public_id.'/submissions';
}

test('anonymous submissions persist all three types and return only confirmation', function () {
    $form = publicResponseForm(true);
    $payload = publicSubmissionPayload($form);
    $payload['contact_email'] = 'participante@example.com';
    $payload['form_id'] = 999;
    $payload['user_id'] = 999;
    $this->postJson(publicSubmissionUrl($form), $payload)->assertCreated()
        ->assertExactJson(['message' => 'Resposta enviada com sucesso.']);
    $this->assertGuest();

    $submission = Submission::sole();
    expect($submission->form_id)->toBe($form->id);
    expect($submission->contact_email)->toBe('participante@example.com');
    expect($submission->answers)->toHaveCount(3);
    $this->assertDatabaseHas('answers', ['submission_id' => $submission->id, 'question_id' => $form->questions[0]->id, 'text' => 'Minha opinião', 'justification' => null]);
    $this->assertDatabaseHas('answers', ['submission_id' => $submission->id, 'question_id' => $form->questions[2]->id, 'text' => null, 'justification' => 'Meu motivo']);
    $this->assertDatabaseCount('answer_options', 3);
    expect($submission->answers->firstWhere('question_id', $form->questions[1]->id)->options->pluck('id')->sort()->values()->all())
        ->toBe($form->questions[1]->options->pluck('id')->sort()->values()->all());
    $public = $this->getJson('/api/v1/public/forms/'.$form->public_id)->assertOk();
    expect($public->getContent())->not->toContain('participante@example.com', 'Minha opinião', 'Meu motivo');
});

test('requested contact email remains optional', function (mixed $email) {
    $form = publicResponseForm(true);
    $payload = publicSubmissionPayload($form);
    if ($email !== 'omit') {
        $payload['contact_email'] = $email;
    }
    $this->postJson(publicSubmissionUrl($form), $payload)->assertCreated();
    expect(Submission::sole()->contact_email)->toBeNull();
})->with(['omit', '', null]);

test('email is not collected when disabled and invalid email does not persist', function (bool $enabled, mixed $email) {
    $form = publicResponseForm($enabled);
    $this->postJson(publicSubmissionUrl($form), [...publicSubmissionPayload($form), 'contact_email' => $email])
        ->assertUnprocessable()->assertJsonValidationErrors('contact_email');
    $this->assertDatabaseCount('submissions', 0);
})->with([
    [false, 'participant@example.com'], [false, []], [true, 'invalid'], [true, []], [true, str_repeat('a', 245).'@example.com'],
]);

test('invalid answer payloads do not write a partial submission', function (Closure $change, string $error) {
    $form = publicResponseForm();
    $payload = $change(publicSubmissionPayload($form), $form);
    $this->postJson(publicSubmissionUrl($form), $payload)->assertUnprocessable()->assertJsonValidationErrors($error);
    $this->assertDatabaseCount('submissions', 0);
    $this->assertDatabaseCount('answers', 0);
    $this->assertDatabaseCount('answer_options', 0);
})->with([
    'missing answers' => [fn ($p) => [], 'answers'],
    'null answers' => [fn ($p) => ['answers' => null], 'answers'],
    'object answers' => [fn ($p) => ['answers' => ['other' => $p['answers'][0]]], 'answers'],
    'unanswered question' => [fn ($p) => ['answers' => array_slice($p['answers'], 0, 2)], 'answers'],
    'duplicate question' => [function ($p) {
        $p['answers'][2] = $p['answers'][0];

        return $p;
    }, 'answers.2.question_id'],
    'foreign question' => [function ($p) {
        $p['answers'][0]['question_id'] = 999999;

        return $p;
    }, 'answers.0.question_id'],
    'malformed question id' => [function ($p) {
        $p['answers'][0]['question_id'] = [];

        return $p;
    }, 'answers.0.question_id'],
    'boolean question id cannot bypass required text' => [function ($p) {
        $p['answers'][0] = ['question_id' => true];

        return $p;
    }, 'answers.0.question_id'],
    'float question id cannot bypass required text' => [function ($p) {
        $p['answers'][0] = ['question_id' => 1.5];

        return $p;
    }, 'answers.0.question_id'],
    'numeric string question id' => [function ($p) {
        $p['answers'][0]['question_id'] = (string) $p['answers'][0]['question_id'];

        return $p;
    }, 'answers.0.question_id'],
    'missing text' => [function ($p) {
        unset($p['answers'][0]['text']);

        return $p;
    }, 'answers.0.text'],
    'blank text' => [function ($p) {
        $p['answers'][0]['text'] = '  ';

        return $p;
    }, 'answers.0.text'],
    'long text' => [function ($p) {
        $p['answers'][0]['text'] = str_repeat('a', 10001);

        return $p;
    }, 'answers.0.text'],
    'empty multiple choice' => [function ($p) {
        $p['answers'][1]['option_ids'] = [];

        return $p;
    }, 'answers.1.option_ids'],
    'null multiple choice' => [function ($p) {
        $p['answers'][1]['option_ids'] = null;

        return $p;
    }, 'answers.1.option_ids'],
    'duplicate option' => [function ($p) {
        $p['answers'][1]['option_ids'] = [$p['answers'][1]['option_ids'][0], $p['answers'][1]['option_ids'][0]];

        return $p;
    }, 'answers.1.option_ids.1'],
    'foreign option' => [function ($p, $f) {
        $p['answers'][1]['option_ids'] = [$f->questions[2]->options[0]->id];

        return $p;
    }, 'answers.1.option_ids.0'],
    'unavailable option' => [function ($p) {
        $p['answers'][1]['option_ids'] = [999999];

        return $p;
    }, 'answers.1.option_ids.0'],
    'option object' => [function ($p) {
        $p['answers'][1]['option_ids'] = ['x' => 1];

        return $p;
    }, 'answers.1.option_ids'],
    'single choice requires one' => [function ($p, $f) {
        $p['answers'][2]['option_ids'] = $f->questions[2]->options->pluck('id')->all();

        return $p;
    }, 'answers.2.option_ids'],
    'missing justification' => [function ($p) {
        unset($p['answers'][2]['justification']);

        return $p;
    }, 'answers.2.justification'],
    'blank justification' => [function ($p) {
        $p['answers'][2]['justification'] = '';

        return $p;
    }, 'answers.2.justification'],
    'long justification' => [function ($p) {
        $p['answers'][2]['justification'] = str_repeat('a', 10001);

        return $p;
    }, 'answers.2.justification'],
    'forged answer id' => [function ($p) {
        $p['answers'][0]['id'] = 5;

        return $p;
    }, 'answers.0'],
    'discursive options' => [function ($p) {
        $p['answers'][0]['option_ids'] = [1];

        return $p;
    }, 'answers.0.option_ids'],
    'objective text' => [function ($p) {
        $p['answers'][1]['text'] = 'extra';

        return $p;
    }, 'answers.1.text'],
    'objective malformed empty text' => [function ($p) {
        $p['answers'][1]['text'] = [];

        return $p;
    }, 'answers.1.text'],
    'multiple justification' => [function ($p) {
        $p['answers'][1]['justification'] = 'extra';

        return $p;
    }, 'answers.1.justification'],
]);

test('a real question from another form cannot be answered through this public link', function () {
    $form = publicResponseForm();
    $other = publicResponseForm();
    $payload = publicSubmissionPayload($form);
    $payload['answers'][0]['question_id'] = $other->questions[0]->id;
    $this->postJson(publicSubmissionUrl($form), $payload)->assertUnprocessable()->assertJsonValidationErrors('answers.0.question_id');
    $this->assertDatabaseCount('submissions', 0);
});

test('multiple choice accepts all alternatives and texts accept multibyte boundaries', function () {
    $form = publicResponseForm();
    foreach (range(1, 25) as $index) {
        $form->questions[1]->options()->create(['label' => 'Opção '.$index, 'position' => $index + 2]);
    }
    $form->load('questions.options');
    $payload = publicSubmissionPayload($form);
    $payload['answers'][0]['text'] = str_repeat('á', 10000);
    $payload['answers'][2]['justification'] = str_repeat('ç', 10000);
    $this->postJson(publicSubmissionUrl($form), $payload)->assertCreated();
    $this->assertDatabaseCount('answer_options', 28);
});

test('forms without questions accept an empty response and repeated submissions stay independent', function () {
    $form = User::factory()->create()->forms()->create(['title' => 'Sem perguntas', 'description' => 'Descrição']);
    foreach (range(1, 2) as $i) {
        $this->postJson(publicSubmissionUrl($form), ['answers' => []])->assertCreated();
    }
    expect($form->submissions)->toHaveCount(2);
    $this->assertDatabaseCount('answers', 0);
});

test('a write failure rolls back submission answers and selected alternatives', function () {
    $form = publicResponseForm();
    $event = 'eloquent.creating: '.Answer::class;
    Answer::creating(function (Answer $answer) use ($form) {
        if ($answer->question_id === $form->questions[2]->id) {
            throw new RuntimeException('Controlled write failure');
        }
    });
    try {
        config(['app.debug' => false]);
        $this->postJson(publicSubmissionUrl($form), publicSubmissionPayload($form))->assertStatus(500);
    } finally {
        Answer::getEventDispatcher()->forget($event);
    }
    $this->assertDatabaseCount('submissions', 0);
    $this->assertDatabaseCount('answers', 0);
    $this->assertDatabaseCount('answer_options', 0);
});

test('submission throttling returns JSON 429 without adding another record', function () {
    $form = User::factory()->create()->forms()->create(['title' => 'Limite técnico', 'description' => 'Descrição']);
    foreach (range(1, 60) as $i) {
        $this->postJson(publicSubmissionUrl($form), ['answers' => []])->assertCreated();
    }
    $this->postJson(publicSubmissionUrl($form), ['answers' => []])->assertStatus(429)
        ->assertHeader('Content-Type', 'application/json')->assertHeader('Retry-After');
    $this->assertDatabaseCount('submissions', 60);
});

describe('anonymous session and CSRF', function () {
    beforeEach(function () {
        $this->withoutVite();
        $this->app->bind(PreventRequestForgery::class, EnforceRequestForgeryProtection::class);
    });

    test('a visitor obtains cookies from the public GET and submits without logging in', function () {
        $form = publicResponseForm();
        $cookie = $this->get('/f/'.$form->public_id)->assertOk()->getCookie('XSRF-TOKEN', decrypt: false)->getValue();
        $this->postJson(publicSubmissionUrl($form), publicSubmissionPayload($form), [
            'X-XSRF-TOKEN' => $cookie,
        ])->assertCreated();
        $this->assertGuest();
        $this->assertDatabaseCount('submissions', 1);
    });

    test('public GET API also provides XSRF cookie for anonymous API clients', function () {
        $form = publicResponseForm();
        $cookie = $this->getJson('/api/v1/public/forms/'.$form->public_id)->assertOk()->getCookie('XSRF-TOKEN', decrypt: false)->getValue();
        $this->postJson(publicSubmissionUrl($form), publicSubmissionPayload($form), ['X-XSRF-TOKEN' => $cookie])->assertCreated();
    });

    test('missing or invalid CSRF rejects the anonymous submission', function (?string $token) {
        $form = publicResponseForm();
        $headers = $token === null ? [] : ['X-XSRF-TOKEN' => $token];
        $this->withSession(['_token' => 'test-csrf'])->postJson(publicSubmissionUrl($form), publicSubmissionPayload($form), $headers)
            ->assertStatus(419)->assertJsonStructure(['message']);
        $this->assertDatabaseCount('submissions', 0);
    })->with([null, 'invalid']);
});

test('a float question id cannot bypass validation of a required answer', function () {
    $form = publicResponseForm();
    $payload = publicSubmissionPayload($form);
    $payload['answers'][0] = ['question_id' => (float) $form->questions[0]->id];
    $this->call('POST', publicSubmissionUrl($form), [], [], [], [
        'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
    ], json_encode($payload, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR))
        ->assertUnprocessable()->assertJsonValidationErrors('answers.0.question_id');
    $this->assertDatabaseCount('submissions', 0);
});

test('whitespace justification and options from another form are rejected without writes', function (string $case) {
    $form = publicResponseForm();
    $other = publicResponseForm();
    $payload = publicSubmissionPayload($form);
    if ($case === 'whitespace') {
        $payload['answers'][2]['justification'] = " \t\n ";
        $error = 'answers.2.justification';
    } else {
        $payload['answers'][2]['option_ids'] = [$other->questions[2]->options[0]->id];
        $error = 'answers.2.option_ids.0';
    }
    $this->postJson(publicSubmissionUrl($form), $payload)->assertUnprocessable()->assertJsonValidationErrors($error);
    $this->assertDatabaseCount('submissions', 0);
    $this->assertDatabaseCount('answers', 0);
    $this->assertDatabaseCount('answer_options', 0);
})->with(['whitespace', 'foreign option']);
