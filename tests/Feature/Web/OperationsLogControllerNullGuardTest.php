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

it('renders an empty combat history instead of erroring when the user has no organization', function () {
    User::create([
        'name' => 'Operator',
        'email' => 'op@diffops.test',
        'password' => 'secret',
        'supabase_uid' => TestJwtSigner::SUB,
    ]);

    $this->withUnencryptedCookie('diffops_session', TestJwtSigner::sign())
        ->get('/operations-log')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('OperationsLog/Index')
            ->where('logs', [])
            ->where('filters', ['actions' => [], 'entityTypes' => []]));
});

it('streams an empty csv export when the user has no organization', function () {
    User::create([
        'name' => 'Operator',
        'email' => 'op@diffops.test',
        'password' => 'secret',
        'supabase_uid' => TestJwtSigner::SUB,
    ]);

    $this->withUnencryptedCookie('diffops_session', TestJwtSigner::sign())
        ->get('/operations-log/export')
        ->assertOk();
});
