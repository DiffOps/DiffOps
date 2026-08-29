<?php

use App\Models\Organization;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

it('renders an empty repository list instead of erroring when the user has no organization', function () {
    User::create([
        'name' => 'Operator',
        'email' => 'op@diffops.test',
        'password' => 'secret',
        'supabase_uid' => TestJwtSigner::SUB,
    ]);

    $this->withUnencryptedCookie('diffops_session', TestJwtSigner::sign())
        ->get('/repos')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Repositories/Index'));
});

it('aborts with 403 when viewing a repository without an active organization', function () {
    User::create([
        'name' => 'Operator',
        'email' => 'op@diffops.test',
        'password' => 'secret',
        'supabase_uid' => TestJwtSigner::SUB,
    ]);

    $org = Organization::create(['name' => 'Alpha', 'slug' => 'alpha']);
    $repo = Repository::create([
        'organization_id' => $org->id,
        'github_repo_id' => 1,
        'full_name' => 'alpha/repo',
    ]);

    $this->withUnencryptedCookie('diffops_session', TestJwtSigner::sign())
        ->get("/repos/{$repo->id}")
        ->assertForbidden();
});
