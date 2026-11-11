<?php

use App\Actions\Forms\CreateForm;
use App\Actions\Submissions\CreateSubmission;
use App\Models\Form;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function readableForm(): Form
{
    return app(CreateForm::class)->handle(User::factory()->create(), 'Pesquisa', 'Descrição', [
        ['type' => 'discursive', 'prompt' => 'Opinião'],
        ['type' => 'multiple_choice', 'prompt' => 'Áreas', 'options' => [['label' => 'A'], ['label' => 'B']]],
        ['type' => 'single_choice_with_justification', 'prompt' => 'Preferida', 'options' => [['label' => 'C']]],
    ], true);
}

function readableSubmission(Form $form): void
{
    app(CreateSubmission::class)->handle($form, [
        ['question_id' => $form->questions[0]->id, 'text' => '<script>opinião</script>'],
        ['question_id' => $form->questions[1]->id, 'option_ids' => $form->questions[1]->options->pluck('id')->all()],
        ['question_id' => $form->questions[2]->id, 'option_ids' => [$form->questions[2]->options[0]->id], 'justification' => 'Motivo'],
    ], 'respondente@example.com');
}

test('general API reads require authentication and do not disclose responses to guests', function () {
    $form = readableForm();
    readableSubmission($form);
    foreach (['/api/v1/forms', '/api/v1/forms/'.$form->id, '/api/v1/forms/'.$form->id.'/submissions'] as $url) {
        $this->get($url)->assertUnauthorized()->assertJsonStructure(['message'])
            ->assertHeaderMissing('Location')->assertDontSee('respondente@example.com')->assertDontSee('Motivo');
    }
    $this->getJson('/api/v1/public/forms/'.$form->public_id)->assertOk()
        ->assertJsonMissingPath('data.submissions')->assertDontSee('respondente@example.com')->assertDontSee('Motivo');
});

test('any authenticated user reads all forms and details without changing the mine filter', function () {
    $form = readableForm();
    $reader = User::factory()->create();
    $this->actingAs($reader)->getJson('/api/v1/forms?creator_id='.$reader->id)
        ->assertOk()->assertJsonPath('data.0.id', $form->id)->assertJsonMissingPath('data.0.questions');
    $this->getJson('/api/v1/forms/mine')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/v1/forms/'.$form->id)->assertOk()
        ->assertJsonPath('data.creator_id', $form->creator_id)->assertJsonCount(3, 'data.questions')
        ->assertJsonPath('data.questions.0.prompt', 'Opinião')->assertJsonPath('data.questions.0.options', [])
        ->assertJsonPath('data.questions.1.options.0.label', 'A')
        ->assertJsonPath('data.questions.2.type', 'single_choice_with_justification')
        ->assertJsonMissingPath('data.creator')->assertJsonMissingPath('data.submissions');
});

test('responses expose all answer types only for the requested form', function () {
    $form = readableForm();
    $other = readableForm();
    readableSubmission($form);
    readableSubmission($other);
    $submission = $form->submissions()->sole();
    $this->actingAs(User::factory()->create())->getJson('/api/v1/forms/'.$form->id.'/submissions')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $submission->id)->assertJsonPath('data.0.form_id', $form->id)
        ->assertJsonPath('data.0.contact_email', 'respondente@example.com')
        ->assertJsonPath('data.0.created_at', $submission->created_at->toISOString())
        ->assertJsonCount(3, 'data.0.answers')
        ->assertJsonPath('data.0.answers.0.question_id', $form->questions[0]->id)
        ->assertJsonPath('data.0.answers.0.text', '<script>opinião</script>')
        ->assertJsonPath('data.0.answers.0.options', [])
        ->assertJsonPath('data.0.answers.1.options.0.label', 'A')
        ->assertJsonCount(2, 'data.0.answers.1.options')
        ->assertJsonPath('data.0.answers.2.options.0.id', $form->questions[2]->options[0]->id)
        ->assertJsonPath('data.0.answers.2.justification', 'Motivo');
});

test('general forms and responses paginate twelve newest records with stable ties', function () {
    $user = User::factory()->create();
    $formIds = [];
    foreach (range(1, 13) as $number) {
        $form = $user->forms()->create(['title' => 'Form '.$number, 'description' => 'Descrição']);
        $form->forceFill(['created_at' => '2026-01-01 12:00:00'])->save();
        $formIds[] = $form->id;
    }
    $this->actingAs($user)->getJson('/api/v1/forms')->assertOk()->assertJsonCount(12, 'data')
        ->assertJsonPath('data.0.id', $formIds[12])->assertJsonPath('meta.total', 13)->assertJsonPath('meta.last_page', 2);
    $this->getJson('/api/v1/forms?page=2')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $formIds[0]);
    $submissionIds = [];
    foreach (range(1, 13) as $number) {
        $submission = $form->submissions()->create();
        $submission->forceFill(['created_at' => '2026-01-01 12:00:00'])->save();
        $submissionIds[] = $submission->id;
    }
    $url = '/api/v1/forms/'.$form->id.'/submissions';
    $this->getJson($url)->assertOk()->assertJsonCount(12, 'data')
        ->assertJsonPath('data.0.id', $submissionIds[12])->assertJsonPath('data.0.answers', [])
        ->assertJsonPath('meta.total', 13)->assertJsonPath('meta.per_page', 12);
    $this->getJson($url.'?page=2')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $submissionIds[0]);
});

test('empty collections and missing forms return appropriate results', function () {
    $this->actingAs(User::factory()->create())->getJson('/api/v1/forms')->assertOk()->assertJsonPath('data', []);
    $form = readableForm();
    $this->getJson('/api/v1/forms/'.$form->id.'/submissions')->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.total', 0);
    $this->getJson('/api/v1/forms/999999')->assertNotFound();
    $this->getJson('/api/v1/forms/999999/submissions')->assertNotFound();
});

test('general reads reject invalid pagination', function (string $page) {
    $form = readableForm();
    $this->actingAs(User::factory()->create());
    foreach (['/api/v1/forms', '/api/v1/forms/'.$form->id.'/submissions'] as $url) {
        $this->getJson($url.'?page='.$page)->assertUnprocessable()->assertJsonValidationErrors('page');
    }
})->with(['0', '-1', 'abc', '1.5', '']);

test('general pages require login and render for users other than the creator', function () {
    $this->withoutVite();
    $form = readableForm();
    $this->get('/forms')->assertRedirect('/login');
    $this->get('/forms/'.$form->id)->assertRedirect('/login');
    $this->actingAs(User::factory()->create())->get('/forms')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('forms/Index'));
    $this->get('/forms/'.$form->id)->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('forms/Show')->where('formId', $form->id));
    $this->get('/forms/999999')->assertNotFound();
});
