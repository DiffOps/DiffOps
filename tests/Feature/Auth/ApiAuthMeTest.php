<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
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

it('registers the api auth me route', function () {
    expect(Route::has('api.auth.me'))->toBeTrue()
        ->and(Route::getRoutes()->getByName('api.auth.me')->uri())->toBe('api/auth/me');
});

it('returns the operator profile for an authenticated user', function () {
    User::create([
        'name' => 'Operator',
        'email' => 'operator@diffops.test',
        'password' => 'secret',
        'supabase_uid' => TestJwtSigner::SUB,
        'github_username' => 'op-one',
        'avatar_url' => 'https://avatars.example.com/op-one.png',
        'is_commander' => false,
        'last_login_at' => now(),
    ]);

    $this->getJson('/api/auth/me', ['Authorization' => 'Bearer '.TestJwtSigner::sign()])
        ->assertOk()
        ->assertJson([
            'data' => [
                'supabase_uid' => TestJwtSigner::SUB,
                'name' => 'Operator',
                'email' => 'operator@diffops.test',
                'github_username' => 'op-one',
                'avatar_url' => 'https://avatars.example.com/op-one.png',
                'is_commander' => false,
                'organizations' => [],
            ],
        ])
        ->assertJsonStructure(['data' => ['last_login_at']])
        ->assertJsonMissingPath('data.is_active');
});

it('returns the organizations with their tactical roles', function () {
    $user = User::create([
        'name' => 'Commander',
        'email' => 'cmdr@diffops.test',
        'password' => 'secret',
        'supabase_uid' => TestJwtSigner::SUB,
        'is_commander' => true,
    ]);

    $alpha = Organization::create(['name' => 'Alpha Unit', 'slug' => 'alpha-unit']);
    $bravo = Organization::create(['name' => 'Bravo Unit', 'slug' => 'bravo-unit']);

    OrganizationMember::create([
        'organization_id' => $alpha->id,
        'user_id' => $user->id,
        'role' => OrganizationRole::Commander->value,
    ]);
    OrganizationMember::create([
        'organization_id' => $bravo->id,
        'user_id' => $user->id,
        'role' => OrganizationRole::Operator->value,
    ]);

    $this->getJson('/api/auth/me', ['Authorization' => 'Bearer '.TestJwtSigner::sign()])
        ->assertOk()
        ->assertJson([
            'data' => [
                'organizations' => [
                    ['id' => $alpha->id, 'name' => 'Alpha Unit', 'role' => 'commander'],
                    ['id' => $bravo->id, 'name' => 'Bravo Unit', 'role' => 'operator'],
                ],
            ],
        ]);
});

it('rejects a request without a token with the unauthenticated contract', function () {
    $this->getJson('/api/auth/me')
        ->assertStatus(401)
        ->assertExactJson(['message' => 'Unauthenticated.']);
});

it('rejects a request with an invalid token', function () {
    $this->getJson('/api/auth/me', ['Authorization' => 'Bearer '.TestJwtSigner::malformed()])
        ->assertStatus(401);
});

it('keeps the web root serving as a smoke check', function () {
    $this->get('/')->assertOk();
});
