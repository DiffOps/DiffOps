<?php

use App\Models\User;
use App\Services\ProfileSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Support\TestJwtSigner;
use Tests\Support\TestUserProfileFixture;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('services.supabase', [
        'url' => TestJwtSigner::ISSUER_BASE,
        'jwt_secret' => TestJwtSigner::SECRET,
        'jwt_audience' => TestJwtSigner::AUDIENCE,
        'jwt_clock_skew' => 30,
        'jwks_url' => TestJwtSigner::ISSUER_BASE.'/auth/v1/.well-known/jwks.json',
        'jwks_cache_ttl' => 3600,
        'jwks_timeout' => 5,
        'last_login_debounce' => 300,
        'profile_sync_http' => true,
        'profile_sync_url' => TestJwtSigner::ISSUER_BASE.'/auth/v1/user',
        'profile_sync_timeout' => 5,
        'profile_sync_cache_ttl' => 300,
    ]);

    Cache::flush();
});

function profileHttpClaims(array $overrides = []): array
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

function profileHttpSyncUrl(): string
{
    return TestJwtSigner::ISSUER_BASE.'/auth/v1/user';
}

it('creates the user with the fetched fields when the claims lack them', function (): void {
    Http::fake([profileHttpSyncUrl() => Http::response(TestUserProfileFixture::payload())]);

    $user = (new ProfileSyncService)->createFromClaims(
        profileHttpClaims([
            'email' => null,
            'user_metadata' => ['full_name' => 'Operator One'],
        ]),
        'the-access-token',
    );

    expect($user->email)->toBe(TestUserProfileFixture::EMAIL)
        ->and($user->github_username)->toBe(TestUserProfileFixture::GITHUB_USERNAME)
        ->and($user->avatar_url)->toBe(TestUserProfileFixture::AVATAR);
});

it('refreshes the github and avatar gaps from the fetched profile', function (): void {
    $user = User::create([
        'name' => 'Operator One',
        'email' => 'operator@diffops.test',
        'password' => 'secret',
        'supabase_uid' => TestJwtSigner::SUB,
    ]);

    Http::fake([profileHttpSyncUrl() => Http::response(TestUserProfileFixture::payload())]);

    (new ProfileSyncService)->refreshIfChanged(
        $user,
        profileHttpClaims(['user_metadata' => ['full_name' => 'Operator One']]),
        'the-access-token',
    );

    $user->refresh();

    expect($user->github_username)->toBe(TestUserProfileFixture::GITHUB_USERNAME)
        ->and($user->avatar_url)->toBe(TestUserProfileFixture::AVATAR)
        ->and($user->name)->toBe('Operator One');
});

it('keeps the claims email when both claims and fetch provide one', function (): void {
    Http::fake([profileHttpSyncUrl() => Http::response(TestUserProfileFixture::payload())]);

    $user = (new ProfileSyncService)->createFromClaims(
        profileHttpClaims(['email' => 'claims@diffops.test']),
        'the-access-token',
    );

    expect($user->email)->toBe('claims@diffops.test');
});

it('applies the claims even when the fetch fails on refresh', function (): void {
    $user = User::create([
        'name' => 'Old Name',
        'email' => 'old@diffops.test',
        'password' => 'secret',
        'supabase_uid' => TestJwtSigner::SUB,
    ]);

    Http::fake([profileHttpSyncUrl() => Http::response('boom', 500)]);

    (new ProfileSyncService)->refreshIfChanged(
        $user,
        profileHttpClaims(),
        'the-access-token',
    );

    $user->refresh();

    expect($user->name)->toBe('Operator One')
        ->and($user->email)->toBe('operator@diffops.test');
});

it('creates the user from the claims when the fetch fails', function (): void {
    Http::fake([profileHttpSyncUrl() => Http::response('boom', 500)]);

    $user = (new ProfileSyncService)->createFromClaims(
        profileHttpClaims(),
        'the-access-token',
    );

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->email)->toBe('operator@diffops.test')
        ->and($user->name)->toBe('Operator One');
});

it('never syncs the operational flags from the fetched profile', function (): void {
    $user = User::create([
        'name' => 'Operator',
        'email' => 'operator@diffops.test',
        'password' => 'secret',
        'supabase_uid' => TestJwtSigner::SUB,
        'is_commander' => true,
        'preferences' => ['theme' => 'dark'],
    ]);

    Http::fake([profileHttpSyncUrl() => Http::response(TestUserProfileFixture::payload([
        'app_metadata' => ['provider' => 'github', 'is_commander' => false],
    ]))]);

    (new ProfileSyncService)->refreshIfChanged(
        $user,
        profileHttpClaims(),
        'the-access-token',
    );

    $user->refresh();

    expect($user->is_commander)->toBeTrue()
        ->and($user->preferences)->toBe(['theme' => 'dark'])
        ->and($user->is_active)->toBeTrue();
});

it('never touches the network when the http sync is disabled', function (): void {
    config()->set('services.supabase.profile_sync_http', false);

    Http::fake();

    $user = (new ProfileSyncService)->createFromClaims(profileHttpClaims(), 'the-access-token');

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->email)->toBe('operator@diffops.test');

    Http::assertNothingSent();
});

it('never touches the network without an access token', function (): void {
    Http::fake();

    $user = User::create([
        'name' => 'Operator',
        'email' => 'local-operator@diffops.test',
        'password' => 'secret',
        'supabase_uid' => 'another-subject',
    ]);

    (new ProfileSyncService)->refreshIfChanged(
        $user,
        profileHttpClaims(['sub' => 'another-subject']),
    );

    Http::assertNothingSent();
});
