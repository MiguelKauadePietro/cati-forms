<?php

use App\Models\Form;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('contact email setting is persisted and returned as a boolean', function (mixed $input, bool $expected) {
    $user = User::factory()->create();
    $response = $this->actingAs($user)->postJson('/api/v1/forms', [
        'title' => 'Contato', 'description' => 'Pesquisa CATI',
        'request_contact_email' => $input,
        'questions' => [['type' => 'discursive', 'prompt' => 'Sua opinião?']],
    ])->assertCreated()->assertJsonPath('data.request_contact_email', $expected);

    $form = Form::findOrFail($response->json('data.id'));
    expect($form->request_contact_email)->toBe($expected);
    expect($form->questions()->count())->toBe(1);
    $this->getJson('/api/v1/forms/mine')->assertOk()
        ->assertJsonPath('data.0.request_contact_email', $expected);
})->with([[true, true], [false, false], [1, true], [0, false], ['1', true], ['0', false]]);

test('older requests default to not requesting contact email', function () {
    $response = $this->actingAs(User::factory()->create())->postJson('/api/v1/forms', [
        'title' => 'Compatível', 'description' => 'Sem configuração',
    ])->assertCreated()->assertJsonPath('data.request_contact_email', false);

    expect(Form::findOrFail($response->json('data.id'))->request_contact_email)->toBeFalse();
});

test('invalid contact email settings reject the whole form', function (mixed $value) {
    $this->actingAs(User::factory()->create())->postJson('/api/v1/forms', [
        'title' => 'Inválido', 'description' => 'Não gravar',
        'request_contact_email' => $value,
        'questions' => [['type' => 'discursive', 'prompt' => 'Pergunta']],
    ])->assertUnprocessable()->assertJsonValidationErrors('request_contact_email');

    $this->assertDatabaseCount('forms', 0);
    $this->assertDatabaseCount('questions', 0);
})->with([[null], ['true'], ['false'], ['yes'], [2], [[]], [['enabled' => true]]]);

test('email configuration cannot be created without authentication', function () {
    $this->postJson('/api/v1/forms', [
        'title' => 'Contato', 'description' => 'Pesquisa', 'request_contact_email' => true,
    ])->assertUnauthorized();
    $this->assertDatabaseCount('forms', 0);
});

test('the additive migration preserves existing forms with email disabled', function () {
    // Separate in-memory schema simulates an installation before RF07.
    $originalConnection = DB::getDefaultConnection();
    config(['database.connections.rf07_migration' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
    ]]);
    DB::setDefaultConnection('rf07_migration');
    try {
        Schema::create('users', fn (Blueprint $table) => $table->id());
        (require database_path('migrations/2026_11_02_020137_create_forms_table.php'))->up();
        DB::table('forms')->insert([
            'id' => 1, 'title' => 'Existente', 'description' => 'Preservada',
            'creator_id' => null, 'created_at' => '2026-11-01 12:00:00', 'updated_at' => '2026-11-01 12:00:00',
        ]);

        (require database_path('migrations/2026_11_06_000000_add_request_contact_email_to_forms_table.php'))->up();

        $form = Form::findOrFail(1);
        expect($form->request_contact_email)->toBeFalse();
        expect($form->title)->toBe('Existente');
        expect($form->description)->toBe('Preservada');
        expect($form->created_at->toDateTimeString())->toBe('2026-11-01 12:00:00');
        expect($form->creator_id)->toBeNull();
    } finally {
        DB::setDefaultConnection($originalConnection);
        DB::purge('rf07_migration');
    }
});
