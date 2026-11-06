<?php

use App\Models\Form;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Tests\Support\EnforceRequestForgeryProtection;

beforeEach(function () {
    $this->withoutVite();
});

test('authenticated users create a form with a versioned JSON response', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/v1/forms', [
        'title' => 'Inscrição CATI Jr.',
        'description' => 'Participe do processo seletivo.',
    ])->assertCreated()->assertHeader('Content-Type', 'application/json');

    $form = Form::sole();
    $response->assertExactJson(['data' => [
        'id' => $form->id,
        'public_id' => $form->public_id,
        'public_url' => route('forms.public', ['form' => $form->public_id]),
        'title' => $form->title,
        'description' => $form->description,
        'request_contact_email' => false,
        'creator_id' => $user->id,
        'created_at' => $form->created_at->toISOString(),
        'updated_at' => $form->updated_at->toISOString(),
        'questions' => [],
    ]]);
    expect($form->creator->is($user))->toBeTrue();
    expect($user->forms()->sole()->is($form))->toBeTrue();
});

test('form creation uses the session user and ignores unvalidated fields', function () {
    $creator = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($creator)->postJson('/api/v1/forms', [
        'id' => 999, 'title' => 'Título', 'description' => 'Descrição',
        'creator_id' => $other->id, 'created_at' => '2000-01-01',
    ])->assertCreated()->assertJsonPath('data.creator_id', $creator->id);

    expect(Form::sole()->id)->not->toBe(999);
    expect(Form::sole()->created_at->year)->not->toBe(2000);
});

test('invalid form input returns field errors without writing data', function (array $overrides, string $field) {
    $this->actingAs(User::factory()->create())->postJson('/api/v1/forms', array_replace([
        'title' => 'Título', 'description' => 'Descrição',
    ], $overrides))->assertUnprocessable()->assertJsonValidationErrors($field);

    $this->assertDatabaseCount('forms', 0);
})->with([
    'missing title' => [['title' => null], 'title'],
    'blank title' => [['title' => '   '], 'title'],
    'invalid title type' => [['title' => ['Título']], 'title'],
    'long title' => [['title' => str_repeat('a', 256)], 'title'],
    'missing description' => [['description' => null], 'description'],
    'blank description' => [['description' => '   '], 'description'],
    'invalid description type' => [['description' => ['Descrição']], 'description'],
    'long description' => [['description' => str_repeat('a', 10001)], 'description'],
]);

test('required form fields cannot be omitted', function () {
    $this->actingAs(User::factory()->create())->postJson('/api/v1/forms', [])
        ->assertUnprocessable()->assertJsonValidationErrors(['title', 'description']);
    $this->assertDatabaseCount('forms', 0);
});

test('form field limits accept multibyte text at their boundaries', function () {
    $this->actingAs(User::factory()->create())->postJson('/api/v1/forms', [
        'title' => str_repeat('á', 255), 'description' => str_repeat('ç', 10000),
    ])->assertCreated();
    $this->assertDatabaseCount('forms', 1);
});

test('any authenticated user can create forms with repeated titles', function () {
    foreach (User::factory()->count(2)->create() as $user) {
        $this->actingAs($user)->postJson('/api/v1/forms', [
            'title' => 'Mesmo título', 'description' => 'Descrição',
        ])->assertCreated()->assertJsonPath('data.creator_id', $user->id);
    }
    $this->assertDatabaseCount('forms', 2);
});

test('deleting an account preserves its forms and removes the creator link', function () {
    $user = User::factory()->create();
    $form = $user->forms()->create(['title' => 'Preservado', 'description' => 'Descrição']);

    $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect('/');

    expect($user->fresh())->toBeNull();
    expect($form->fresh()->creator_id)->toBeNull();
    expect($form->fresh()->title)->toBe('Preservado');
});

describe('session and CSRF protection', function () {
    beforeEach(function () {
        $this->app->bind(PreventRequestForgery::class, EnforceRequestForgeryProtection::class);
    });

    test('guest receives JSON 401 with a valid CSRF token even without Accept header', function () {
        $this->withSession(['_token' => 'csrf-probe'])->post('/api/v1/forms', [
            'title' => 'Título', 'description' => 'Descrição',
        ], ['X-CSRF-TOKEN' => 'csrf-probe'])
            ->assertUnauthorized()->assertJsonStructure(['message'])->assertHeaderMissing('Location');
        $this->assertDatabaseCount('forms', 0);
    });

    test('missing or invalid token returns JSON 419 without writing data', function (?string $token) {
        $headers = $token === null ? [] : ['X-XSRF-TOKEN' => $token];
        $this->actingAs(User::factory()->create())->withSession(['_token' => 'csrf-probe'])
            ->postJson('/api/v1/forms', ['title' => 'Título', 'description' => 'Descrição'], $headers)
            ->assertStatus(419)->assertJsonStructure(['message']);
        $this->assertDatabaseCount('forms', 0);
    })->with([null, 'invalid-encrypted-token']);

    test('native same-origin verification permits an authenticated request without a token header', function () {
        $this->actingAs(User::factory()->create())->postJson('/api/v1/forms', [
            'title' => 'Origem válida', 'description' => 'Descrição',
        ], ['Sec-Fetch-Site' => 'same-origin'])->assertCreated();
    });

    test('Fortify login session and its XSRF cookie authenticate API creation', function () {
        $user = User::factory()->create();
        $this->get('/login');
        $this->post('/login', ['username' => $user->username, 'password' => 'password'], [
            'X-CSRF-TOKEN' => session()->token(),
        ])->assertRedirect('/dashboard');

        // The browser sends the encrypted XSRF-TOKEN cookie value as this header.
        $cookie = $this->get('/forms/create')->assertOk()->getCookie('XSRF-TOKEN', decrypt: false)->getValue();
        $this->postJson('/api/v1/forms', ['title' => 'Título', 'description' => 'Descrição'], [
            'X-XSRF-TOKEN' => $cookie,
        ])->assertCreated()->assertJsonPath('data.creator_id', $user->id);
    });
});
