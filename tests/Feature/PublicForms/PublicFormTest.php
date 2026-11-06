<?php

use App\Actions\Forms\CreateForm;
use App\Models\Form;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('new forms have distinct permanent UUID v4 links defined by the server', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->postJson('/api/v1/forms', [
        'title' => 'Compartilhar', 'description' => 'Descrição',
        'public_id' => (string) Str::uuid(),
    ])->assertCreated()->assertJsonPath('data.public_id', fn ($id) => Str::isUuid($id, 4));
    $form = Form::sole();
    $publicId = $form->public_id;
    $second = $user->forms()->create(['title' => 'Outro', 'description' => 'Descrição']);
    expect($second->public_id)->not->toBe($publicId);

    $this->getJson('/api/v1/forms/mine')->assertOk()->assertJsonPath('data.1.public_url', route('forms.public', ['form' => $publicId]));
    $this->post('/logout');
    $this->get(route('forms.public', ['form' => $publicId]))->assertOk();
    expect($form->fresh()->public_id)->toBe($publicId);
});

test('visitors and authenticated users receive a public page and only public form data', function (bool $authenticated) {
    $form = app(CreateForm::class)->handle(User::factory()->create(), 'Pesquisa', 'Descrição', [
        ['type' => 'discursive', 'prompt' => 'Opinião?'],
        ['type' => 'multiple_choice', 'prompt' => 'Áreas?', 'options' => [['label' => 'A'], ['label' => 'B']]],
        ['type' => 'single_choice_with_justification', 'prompt' => 'Preferida?', 'options' => [['label' => 'C']]],
    ], true);
    if ($authenticated) {
        $this->actingAs(User::factory()->create());
    }

    $this->get('/f/'.$form->public_id)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('forms/Public')->where('publicId', $form->public_id));
    $response = $this->getJson('/api/v1/public/forms/'.$form->public_id)->assertOk()
        ->assertJsonPath('data.title', 'Pesquisa')->assertJsonPath('data.request_contact_email', true)
        ->assertJsonPath('data.questions.0.type', 'discursive')
        ->assertJsonPath('data.questions.1.type', 'multiple_choice')
        ->assertJsonPath('data.questions.2.type', 'single_choice_with_justification')
        ->assertJsonPath('data.questions.1.options.0.label', 'A');
    expect(array_keys($response->json('data')))->toBe(['public_id', 'title', 'description', 'request_contact_email', 'questions']);
    expect(array_keys($response->json('data.questions.0')))->toBe(['id', 'type', 'prompt', 'position', 'options']);
    expect(array_keys($response->json('data.questions.1.options.0')))->toBe(['id', 'label', 'position']);
})->with([false, true]);

test('invalid or unknown public links return 404 instead of exposing forms by numeric id', function (string $identifier) {
    User::factory()->create()->forms()->create(['title' => 'Existente', 'description' => 'Descrição']);
    $this->get('/f/'.$identifier)->assertNotFound();
    $this->getJson('/api/v1/public/forms/'.$identifier)->assertNotFound()->assertJsonStructure(['message']);
    $this->postJson('/api/v1/public/forms/'.$identifier.'/submissions', ['answers' => []])->assertNotFound();
})->with(['1', 'invalid', '00000000-0000-4000-8000-000000000000']);

test('public access does not weaken private administrative operations', function () {
    $this->get('/dashboard')->assertRedirect('/login');
    $this->get('/forms/create')->assertRedirect('/login');
    $this->getJson('/api/v1/forms/mine')->assertUnauthorized();
    $this->postJson('/api/v1/forms', ['title' => 'Título', 'description' => 'Descrição'])->assertUnauthorized();
});

test('the public id migration preserves and backfills existing forms', function () {
    $previous = DB::getDefaultConnection();
    config(['database.connections.public_id_migration' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
    ]]);
    DB::setDefaultConnection('public_id_migration');
    try {
        Schema::create('users', fn (Blueprint $table) => $table->id());
        (require database_path('migrations/2026_11_02_020137_create_forms_table.php'))->up();
        DB::table('forms')->insert([
            ['id' => 1, 'title' => 'Existente', 'description' => 'Preservada', 'creator_id' => null],
            ['id' => 2, 'title' => 'Segundo', 'description' => 'Preservada', 'creator_id' => null],
        ]);
        (require database_path('migrations/2026_11_06_010000_add_public_id_to_forms_table.php'))->up();
        $forms = DB::table('forms')->orderBy('id')->get();
        expect($forms)->toHaveCount(2);
        expect($forms[0]->title)->toBe('Existente');
        expect($forms[0]->description)->toBe('Preservada');
        expect(Str::isUuid($forms[0]->public_id, 4))->toBeTrue();
        expect(Str::isUuid($forms[1]->public_id, 4))->toBeTrue();
        expect($forms[0]->public_id)->not->toBe($forms[1]->public_id);
        $column = collect(DB::select("PRAGMA table_info('forms')"))->firstWhere('name', 'public_id');
        expect($column->notnull)->toBe(1);
    } finally {
        DB::setDefaultConnection($previous);
        DB::purge('public_id_migration');
    }
});

test('public link remains valid after the creator account is removed', function () {
    $user = User::factory()->create();
    $form = app(CreateForm::class)->handle($user, 'Preservado', 'Descrição');
    $url = '/f/'.$form->public_id;
    $user->delete();
    expect($form->fresh()->creator_id)->toBeNull();
    expect($form->fresh()->public_id)->toBe($form->public_id);
    $this->get($url)->assertOk();
    $this->getJson('/api/v1/public/forms/'.$form->public_id)->assertOk()->assertJsonPath('data.title', 'Preservado');
});
