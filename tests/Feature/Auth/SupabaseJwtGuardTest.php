<?php

use App\Auth\SupabaseJwtGuard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Support\TestJwtSigner;
use Tests\Support\TestUserProfileFixture;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.supabase', [
        'url' => TestJwtSigner::ISSUER_BASE,
        'jwt_secret' => TestJwtSigner::SECRET,
        'jwt_audience' => TestJwtSigner::AUDIENCE,
        'jwt_clock_skew' => 30,
        'jwks_url' => TestJwtSigner::ISSUER_BASE.'/auth/v1/.well-known/jwks.json',
        'jwks_cache_ttl' => 3600,
        'jwks_timeout' => 5,
        'last_login_debounce' => 300,
    ]);

    Cache::flush();
});

function supabaseGuardRequest(string $authorization): Request
{
    return Request::create('/', 'GET', [], [], [], ['HTTP_AUTHORIZATION' => $authorization]);
}

it('resolves the user from a valid bearer token', function () {
    $user = User::create([
        'name' => 'Operator',
        'email' => 'operator@diffops.test',
        'password' => 'secret',
        'supabase_uid' => TestJwtSigner::SUB,
    ]);

    $this->app->instance('request', supabaseGuardRequest('Bearer '.TestJwtSigner::sign()));

    $guard = Auth::guard('supabase');

    expect($guard->user())->toBeInstanceOf(User::class)
        ->and($guard->user()->supabase_uid)->toBe(TestJwtSigner::SUB)
        ->and($guard->user()->is_active)->toBeTrue()
        ->and($guard->user())->toBe($guard->user());
});

it('creates the local profile when the token subject has no user', function () {
    $this->app->instance('request', supabaseGuardRequest('Bearer '.TestJwtSigner::sign()));

    $user = Auth::guard('supabase')->user();

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->supabase_uid)->toBe(TestJwtSigner::SUB)
        ->and(User::where('supabase_uid', TestJwtSigner::SUB)->exists())->toBeTrue();
});

it('returns null for an inactive user', function () {
    User::create([
        'name' => 'Suspended',
        'email' => 'suspended@diffops.test',
        'password' => 'secret',
        'supabase_uid' => TestJwtSigner::SUB,
        'is_active' => false,
    ]);

    $this->app->instance('request', supabaseGuardRequest('Bearer '.TestJwtSigner::sign()));

    expect(Auth::guard('supabase')->user())->toBeNull();
});

it('returns null without a bearer token', function () {
    $this->app->instance('request', Request::create('/', 'GET'));

    expect(Auth::guard('supabase')->user())->toBeNull();
});

it('returns null for an invalid token without leaking exceptions', function () {
    $this->app->instance('request', supabaseGuardRequest('Bearer '.TestJwtSigner::malformed()));

    expect(Auth::guard('supabase')->user())->toBeNull();
});

it('reports check and guest state', function () {
    User::create([
        'name' => 'Operator',
        'email' => 'operator@diffops.test',
        'password' => 'secret',
        'supabase_uid' => TestJwtSigner::SUB,
    ]);

    $this->app->instance('request', supabaseGuardRequest('Bearer '.TestJwtSigner::sign()));

    $guard = Auth::guard('supabase');

    expect($guard->check())->toBeTrue()
        ->and($guard->guest())->toBeFalse();

    $anonymous = new SupabaseJwtGuard(
        $this->app['auth']->createUserProvider('users'),
        Request::create('/', 'GET'),
    );

    expect($anonymous->check())->toBeFalse()
        ->and($anonymous->guest())->toBeTrue();
});

it('returns the id of the authenticated user', function () {
    $user = User::create([
        'name' => 'Operator',
        'email' => 'operator@diffops.test',
        'password' => 'secret',
        'supabase_uid' => TestJwtSigner::SUB,
    ]);

    $this->app->instance('request', supabaseGuardRequest('Bearer '.TestJwtSigner::sign()));

    expect(Auth::guard('supabase')->id())->toBe($user->id);
});

it('sets a user directly', function () {
    $user = User::create([
        'name' => 'Operator',
        'email' => 'operator@diffops.test',
        'password' => 'secret',
        'supabase_uid' => TestJwtSigner::SUB,
    ]);

    $this->app->instance('request', Request::create('/', 'GET'));

    $guard = Auth::guard('supabase');

    $guard->setUser($user);

    expect($guard->user())->toBe($user)
        ->and($guard->check())->toBeTrue()
        ->and($guard->hasUser())->toBeTrue()
        ->and($guard->id())->toBe($user->id);
});

it('creates the local profile on first login', function () {
    $this->app->instance('request', supabaseGuardRequest('Bearer '.TestJwtSigner::sign([
        'email' => 'operator@diffops.test',
        'user_metadata' => [
            'full_name' => 'Operator One',
            'user_name' => 'op-one',
            'avatar_url' => 'https://avatars.example.com/op-one.png',
        ],
    ])));

    $user = Auth::guard('supabase')->user();

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->supabase_uid)->toBe(TestJwtSigner::SUB)
        ->and($user->name)->toBe('Operator One')
        ->and($user->email)->toBe('operator@diffops.test')
        ->and($user->github_username)->toBe('op-one')
        ->and(User::where('supabase_uid', TestJwtSigner::SUB)->exists())->toBeTrue();
});

it('forwards the bearer token to the profile sync on first login', function () {
    config()->set('services.supabase.profile_sync_http', true);
    config()->set('services.supabase.profile_sync_url', TestJwtSigner::ISSUER_BASE.'/auth/v1/user');
    config()->set('services.supabase.profile_sync_timeout', 5);
    config()->set('services.supabase.profile_sync_cache_ttl', 300);

    $token = TestJwtSigner::sign(['email' => null]);

    Http::fake(['*/auth/v1/user' => Http::response(TestUserProfileFixture::payload())]);

    $this->app->instance('request', supabaseGuardRequest('Bearer '.$token));

    $user = Auth::guard('supabase')->user();

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->email)->toBe(TestUserProfileFixture::EMAIL);

    Http::assertSent(fn (ClientRequest $request): bool => $request->hasHeader('Authorization', 'Bearer '.$token));
});

it('keeps authenticating when the profile fetch fails on first login', function () {
    config()->set('services.supabase.profile_sync_http', true);
    config()->set('services.supabase.profile_sync_url', TestJwtSigner::ISSUER_BASE.'/auth/v1/user');
    config()->set('services.supabase.profile_sync_timeout', 5);
    config()->set('services.supabase.profile_sync_cache_ttl', 300);

    $token = TestJwtSigner::sign([
        'email' => 'operator@diffops.test',
        'user_metadata' => ['full_name' => 'Operator One'],
    ]);

    Http::fake(['*/auth/v1/user' => Http::response('boom', 500)]);

    $this->app->instance('request', supabaseGuardRequest('Bearer '.$token));

    $user = Auth::guard('supabase')->user();

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->supabase_uid)->toBe(TestJwtSigner::SUB)
        ->and($user->email)->toBe('operator@diffops.test');
});
