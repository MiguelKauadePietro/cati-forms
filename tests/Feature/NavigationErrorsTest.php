<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    config(['app.debug' => false]);
    foreach ([403, 500] as $status) {
        Route::middleware('web')->get('/_test/navigation/'.$status, function () use ($status) {
            if ($status === 500) {
                throw new RuntimeException('Internal diagnostic detail');
            }
            abort($status, 'Internal diagnostic detail');
        });
        Route::middleware('web')->get('/api/v1/_test/navigation/'.$status, function () use ($status) {
            abort($status);
        });
    }
});

function inertiaNavigationHeaders(): array
{
    return [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')) ?? '',
        'Accept' => 'text/html, application/xhtml+xml',
    ];
}

test('page errors render Inertia with the original status for guests and users', function (int $status, bool $inertia, bool $authenticated) {
    if ($authenticated) {
        $this->actingAs(User::factory()->create());
    }
    $path = $status === 404 ? '/_missing_page' : '/_test/navigation/'.$status;
    $response = $this->get($path, $inertia ? inertiaNavigationHeaders() : [])
        ->assertStatus($status);

    if ($inertia) {
        $response->assertHeader('X-Inertia', 'true')->assertJsonPath('component', 'Error')
            ->assertJsonPath('props.status', $status)->assertJsonPath('props.appearance', 'system');
    } else {
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Error')->where('status', $status)->where('appearance', 'system'));
    }
    expect($response->getContent())->not->toContain('Internal diagnostic detail');
})->with([403, 404, 500])->with([false, true])->with([false, true]);

test('unmatched pages preserve validated appearance without web middleware', function (string $cookie, string $expected) {
    $this->withUnencryptedCookie('appearance', $cookie)->get('/_missing_page', inertiaNavigationHeaders())
        ->assertNotFound()->assertHeader('X-Inertia', 'true')->assertJsonPath('component', 'Error')
        ->assertJsonPath('props.appearance', $expected);
})->with([['dark', 'dark'], ['invalid', 'system']]);

test('API errors stay JSON even when the caller sends Inertia headers', function (int $status) {
    $path = match ($status) {
        401 => '/api/v1/forms/mine',
        404 => '/api/v1/_missing_route',
        default => '/api/v1/_test/navigation/'.$status,
    };
    $this->get($path, inertiaNavigationHeaders())->assertStatus($status)
        ->assertHeader('Content-Type', 'application/json')->assertHeaderMissing('X-Inertia')
        ->assertJsonStructure(['message'])->assertJsonMissingPath('component');
})->with([401, 403, 404, 500]);

test('explicit JSON requests to web pages also stay JSON', function () {
    $this->getJson('/_missing_page')->assertNotFound()
        ->assertHeader('Content-Type', 'application/json')->assertJsonMissingPath('component');
});

test('protected navigation redirects visitors to the Inertia login page', function (string $path) {
    $this->get($path, inertiaNavigationHeaders())->assertRedirect(route('login'));
    $this->get(route('login'), inertiaNavigationHeaders())->assertOk()
        ->assertHeader('X-Inertia', 'true')->assertJsonPath('component', 'auth/Login');
})->with(['/dashboard', '/forms/create']);

test('debug mode preserves Laravel diagnostic responses for server errors', function () {
    config(['app.debug' => true]);
    $this->get('/_test/navigation/500', inertiaNavigationHeaders())->assertStatus(500)
        ->assertHeaderMissing('X-Inertia');
});
