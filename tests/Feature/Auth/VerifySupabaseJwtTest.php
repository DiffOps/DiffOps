<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\Support\TestJwtSigner;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.supabase', [
        'url' => TestJwtSigner::ISSUER_BASE,
        'jwt_secret' => TestJwtSigner::SECRET,
        'jwt_audience' => TestJwtSigner::AUDIENCE,
        'jwt_clock_skew' => 30,
    ]);

    Route::middleware('verify.supabase.jwt')->get('/_auth/probe', fn () => response()->json([
        'supabase_uid' => auth('supabase')->user()->supabase_uid,
    ]));
});

it('accepts a valid token', function () {
    User::create([
        'name' => 'Operator',
        'email' => 'operator@diffops.test',
        'password' => 'secret',
        'supabase_uid' => TestJwtSigner::SUB,
    ]);

    $this->getJson('/_auth/probe', ['Authorization' => 'Bearer '.TestJwtSigner::sign()])
        ->assertOk()
        ->assertJson(['supabase_uid' => TestJwtSigner::SUB]);
});

it('rejects a request without a token', function () {
    $this->getJson('/_auth/probe')->assertStatus(401);
});

it('rejects a malformed token', function () {
    $this->getJson('/_auth/probe', ['Authorization' => 'Bearer '.TestJwtSigner::malformed()])
        ->assertStatus(401);
});

it('rejects a token signed with another key', function () {
    $this->getJson('/_auth/probe', ['Authorization' => 'Bearer '.TestJwtSigner::sign(key: 'another-diffops-test-secret-0123456789abcdef0123456789abcdef0123456789abcdef')])
        ->assertStatus(401);
});

it('rejects an expired token', function () {
    $this->getJson('/_auth/probe', ['Authorization' => 'Bearer '.TestJwtSigner::sign(['exp' => time() - 3600])])
        ->assertStatus(401);
});

it('rejects a token with a foreign issuer', function () {
    $this->getJson('/_auth/probe', ['Authorization' => 'Bearer '.TestJwtSigner::sign(['iss' => 'https://evil.example.com/auth/v1'])])
        ->assertStatus(401);
});

it('rejects a token with a foreign audience', function () {
    $this->getJson('/_auth/probe', ['Authorization' => 'Bearer '.TestJwtSigner::sign(['aud' => 'service_role'])])
        ->assertStatus(401);
});

it('rejects a token without a subject', function () {
    $this->getJson('/_auth/probe', ['Authorization' => 'Bearer '.TestJwtSigner::sign(['sub' => null])])
        ->assertStatus(401);
});

it('accepts a valid token and creates the local profile on first login', function () {
    $this->getJson('/_auth/probe', ['Authorization' => 'Bearer '.TestJwtSigner::sign()])
        ->assertOk()
        ->assertJson(['supabase_uid' => TestJwtSigner::SUB]);

    expect(User::where('supabase_uid', TestJwtSigner::SUB)->exists())->toBeTrue();
});

it('rejects a token for an inactive user', function () {
    User::create([
        'name' => 'Suspended',
        'email' => 'suspended@diffops.test',
        'password' => 'secret',
        'supabase_uid' => TestJwtSigner::SUB,
        'is_active' => false,
    ]);

    $this->getJson('/_auth/probe', ['Authorization' => 'Bearer '.TestJwtSigner::sign()])
        ->assertStatus(401);
});

it('answers 401 with the unauthenticated json contract', function () {
    $this->getJson('/_auth/probe')
        ->assertStatus(401)
        ->assertExactJson(['message' => 'Unauthenticated.']);
});

it('accepts a valid token even when the profile fetch fails', function () {
    config()->set('services.supabase.profile_sync_http', true);
    config()->set('services.supabase.profile_sync_url', TestJwtSigner::ISSUER_BASE.'/auth/v1/user');
    config()->set('services.supabase.profile_sync_timeout', 5);
    config()->set('services.supabase.profile_sync_cache_ttl', 300);

    Http::fake(['*/auth/v1/user' => Http::response('boom', 500)]);

    $this->getJson('/_auth/probe', ['Authorization' => 'Bearer '.TestJwtSigner::sign()])
        ->assertOk()
        ->assertJson(['supabase_uid' => TestJwtSigner::SUB]);
});
