<?php

use App\Auth\SupabaseJwtGuard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\Support\TestJwtSigner;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.supabase', [
        'url' => TestJwtSigner::ISSUER_BASE,
        'jwt_secret' => TestJwtSigner::SECRET,
        'jwt_audience' => TestJwtSigner::AUDIENCE,
        'jwt_clock_skew' => 30,
    ]);
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

it('returns null when the token subject has no user', function () {
    $this->app->instance('request', supabaseGuardRequest('Bearer '.TestJwtSigner::sign()));

    expect(Auth::guard('supabase')->user())->toBeNull();
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
