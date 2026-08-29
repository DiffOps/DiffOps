<?php

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

it('exposes a not-linked github identity when username is absent', function () {
    User::create([
        'name' => 'Operator',
        'email' => 'op@diffops.test',
        'password' => 'secret',
        'supabase_uid' => TestJwtSigner::SUB,
        'github_username' => null,
    ]);

    $this->withUnencryptedCookie('diffops_session', TestJwtSigner::sign())
        ->get('/settings')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Settings/Index')
            ->where('github.linked', false)
            ->where('github.username', null)
            ->where('github.avatar_url', null));
});

it('exposes a linked github identity when username is present', function () {
    User::create([
        'name' => 'Operator',
        'email' => 'op@diffops.test',
        'password' => 'secret',
        'supabase_uid' => TestJwtSigner::SUB,
        'github_username' => 'octocat',
        'avatar_url' => 'https://avatars.example.com/octocat.png',
    ]);

    $this->withUnencryptedCookie('diffops_session', TestJwtSigner::sign())
        ->get('/settings')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Settings/Index')
            ->where('github.linked', true)
            ->where('github.username', 'octocat')
            ->where('github.avatar_url', 'https://avatars.example.com/octocat.png'));
});
