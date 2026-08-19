<?php

use App\Models\User;
use App\Services\ProfileSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Tests\Support\TestJwtSigner;

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

function profileSyncClaims(array $overrides = []): array
{
    return array_merge([
        'sub' => TestJwtSigner::SUB,
        'email' => 'operator@diffops.test',
        'user_metadata' => [
            'full_name' => 'Operator One',
            'user_name' => 'op-one',
            'avatar_url' => 'https://avatars.example.com/op-one.png',
        ],
    ], $overrides);
}

function profileSyncGuardRequest(string $authorization): Request
{
    return Request::create('/', 'GET', [], [], [], ['HTTP_AUTHORIZATION' => $authorization]);
}

it('creates a local profile on first login', function () {
    $user = (new ProfileSyncService)->createFromClaims(profileSyncClaims());

    expect($user->supabase_uid)->toBe(TestJwtSigner::SUB)
        ->and($user->name)->toBe('Operator One')
        ->and($user->email)->toBe('operator@diffops.test')
        ->and($user->github_username)->toBe('op-one')
        ->and($user->avatar_url)->toBe('https://avatars.example.com/op-one.png')
        ->and($user->last_login_at)->not->toBeNull()
        ->and($user->fresh()->is_active)->toBeTrue();
});

it('updates the profile when the claims change', function () {
    $user = User::create([
        'name' => 'Old Name',
        'email' => 'old@diffops.test',
        'password' => 'secret',
        'supabase_uid' => TestJwtSigner::SUB,
    ]);

    (new ProfileSyncService)->refreshIfChanged($user, profileSyncClaims());

    $user->refresh();

    expect($user->name)->toBe('Operator One')
        ->and($user->email)->toBe('operator@diffops.test')
        ->and($user->github_username)->toBe('op-one')
        ->and($user->avatar_url)->toBe('https://avatars.example.com/op-one.png');
});

it('does not save the profile when nothing changed', function () {
    $user = User::create([
        'name' => 'Operator One',
        'email' => 'operator@diffops.test',
        'password' => 'secret',
        'supabase_uid' => TestJwtSigner::SUB,
        'github_username' => 'op-one',
        'avatar_url' => 'https://avatars.example.com/op-one.png',
    ]);

    Cache::put('supabase:last-login:'.$user->id, now()->timestamp, 300);

    $saves = 0;

    User::updating(function () use (&$saves) {
        $saves++;
    });

    (new ProfileSyncService)->refreshIfChanged($user, profileSyncClaims());

    expect($saves)->toBe(0);
});

it('never overwrites is_active, is_commander or preferences', function () {
    $user = User::create([
        'name' => 'Operator One',
        'email' => 'operator@diffops.test',
        'password' => 'secret',
        'supabase_uid' => TestJwtSigner::SUB,
        'github_username' => 'op-one',
        'avatar_url' => 'https://avatars.example.com/op-one.png',
        'is_commander' => true,
        'preferences' => ['theme' => 'dark'],
    ]);

    Cache::put('supabase:last-login:'.$user->id, now()->timestamp, 300);

    (new ProfileSyncService)->refreshIfChanged($user, profileSyncClaims(['user_metadata' => [
        'full_name' => 'New Name',
        'user_name' => 'new-handle',
    ]]));

    $user->refresh();

    expect($user->name)->toBe('New Name')
        ->and($user->is_active)->toBeTrue()
        ->and($user->is_commander)->toBeTrue()
        ->and($user->preferences)->toBe(['theme' => 'dark']);
});

it('debounces last_login writes within the window', function () {
    $user = User::create([
        'name' => 'Operator One',
        'email' => 'operator@diffops.test',
        'password' => 'secret',
        'supabase_uid' => TestJwtSigner::SUB,
        'github_username' => 'op-one',
        'avatar_url' => 'https://avatars.example.com/op-one.png',
    ]);

    $service = new ProfileSyncService;

    $service->refreshIfChanged($user, profileSyncClaims());
    $first = $user->fresh()->last_login_at;

    sleep(1);

    $service->refreshIfChanged($user, profileSyncClaims());
    $second = $user->fresh()->last_login_at;

    expect($second->equalTo($first))->toBeTrue();

    Cache::forget('supabase:last-login:'.$user->id);

    sleep(1);

    $service->refreshIfChanged($user, profileSyncClaims());
    $third = $user->fresh()->last_login_at;

    expect($third->greaterThan($second))->toBeTrue();
});

it('keeps authenticating when the profile sync fails for an existing user', function () {
    User::create([
        'name' => 'Operator',
        'email' => 'operator@diffops.test',
        'password' => 'secret',
        'supabase_uid' => TestJwtSigner::SUB,
    ]);

    $sync = Mockery::mock(ProfileSyncService::class);
    $sync->shouldReceive('refreshIfChanged')->once()->andThrow(new RuntimeException('sync boom'));

    $this->app->instance(ProfileSyncService::class, $sync);

    $this->app->instance('request', profileSyncGuardRequest('Bearer '.TestJwtSigner::sign()));

    $user = Auth::guard('supabase')->user();

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->supabase_uid)->toBe(TestJwtSigner::SUB);
});

it('keeps returning null for an inactive user', function () {
    User::create([
        'name' => 'Suspended',
        'email' => 'suspended@diffops.test',
        'password' => 'secret',
        'supabase_uid' => TestJwtSigner::SUB,
        'is_active' => false,
    ]);

    $this->app->instance('request', profileSyncGuardRequest('Bearer '.TestJwtSigner::sign()));

    expect(Auth::guard('supabase')->user())->toBeNull();
});
