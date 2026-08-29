<?php

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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
});

it('shares organizations and the active organization for a member', function () {
    $user = User::create([
        'name' => 'Operator',
        'email' => 'op@diffops.test',
        'password' => 'secret',
        'supabase_uid' => TestJwtSigner::SUB,
    ]);
    $org = Organization::create(['name' => 'Alpha', 'slug' => 'alpha']);
    $user->organizations()->attach($org->id, ['id' => Str::uuid()->toString()]);

    $this->withUnencryptedCookie('diffops_session', TestJwtSigner::sign())
        ->get('/settings')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('organizations', [['id' => $org->id, 'name' => 'Alpha', 'slug' => 'alpha']])
            ->where('currentOrganization', ['id' => $org->id, 'name' => 'Alpha', 'slug' => 'alpha']));
});

it('shares an empty org list and null active organization for a memberless user', function () {
    User::create([
        'name' => 'Operator',
        'email' => 'op@diffops.test',
        'password' => 'secret',
        'supabase_uid' => TestJwtSigner::SUB,
    ]);

    $this->withUnencryptedCookie('diffops_session', TestJwtSigner::sign())
        ->get('/settings')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('organizations', [])
            ->where('currentOrganization', null));
});
