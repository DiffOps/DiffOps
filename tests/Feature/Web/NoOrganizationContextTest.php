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

function memberlessUser(): User
{
    return User::create([
        'name' => 'Operator',
        'email' => 'op@diffops.test',
        'password' => 'secret',
        'supabase_uid' => TestJwtSigner::SUB,
    ]);
}

it('renders the dashboard for a user without an organization', function () {
    memberlessUser();

    $this->withUnencryptedCookie('diffops_session', TestJwtSigner::sign())
        ->get('/dashboard')
        ->assertOk();
});

it('renders incursions for a user without an organization', function () {
    memberlessUser();

    $this->withUnencryptedCookie('diffops_session', TestJwtSigner::sign())
        ->get('/incursions')
        ->assertOk();
});

it('renders repositories for a user without an organization', function () {
    memberlessUser();

    $this->withUnencryptedCookie('diffops_session', TestJwtSigner::sign())
        ->get('/repos')
        ->assertOk();
});

it('renders the operations log for a user without an organization', function () {
    memberlessUser();

    $this->withUnencryptedCookie('diffops_session', TestJwtSigner::sign())
        ->get('/operations-log')
        ->assertOk();
});

it('renders the briefing for a user without an organization', function () {
    memberlessUser();

    $this->withUnencryptedCookie('diffops_session', TestJwtSigner::sign())
        ->get('/briefing')
        ->assertOk();
});

it('renders the watchlist for a user without an organization', function () {
    memberlessUser();

    $this->withUnencryptedCookie('diffops_session', TestJwtSigner::sign())
        ->get('/watchlist')
        ->assertOk();
});

it('renders settings for a user without an organization', function () {
    memberlessUser();

    $this->withUnencryptedCookie('diffops_session', TestJwtSigner::sign())
        ->get('/settings')
        ->assertOk();
});
