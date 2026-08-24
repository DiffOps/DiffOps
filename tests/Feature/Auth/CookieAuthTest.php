<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\TestJwtSigner;

uses(RefreshDatabase::class);

const COOKIE_NAME = 'diffops_session';

beforeEach(function (): void {
    config()->set('services.supabase', [
        'url' => TestJwtSigner::ISSUER_BASE,
        'jwt_secret' => TestJwtSigner::SECRET,
        'jwt_audience' => TestJwtSigner::AUDIENCE,
        'jwt_clock_skew' => 30,
    ]);

    // Grupo web para exercitar o caminho real (EncryptCookies ativo antes da rota).
    Route::middleware(['web', 'verify.supabase.jwt'])->get('/_auth/probe-web', fn () => response()->json([
        'supabase_uid' => auth('supabase')->user()->supabase_uid,
    ]));
});

function seedOperator(): User
{
    return User::create([
        'name' => 'Operator',
        'email' => 'operator@diffops.test',
        'password' => 'secret',
        'supabase_uid' => TestJwtSigner::SUB,
    ]);
}

it('authenticates from an encrypted session cookie when no bearer is present', function (): void {
    seedOperator();

    $this->withUnencryptedCookie(COOKIE_NAME, TestJwtSigner::sign())
        ->get('/_auth/probe-web')
        ->assertOk()
        ->assertJson(['supabase_uid' => TestJwtSigner::SUB]);
});

it('rejects an invalid session cookie', function (): void {
    seedOperator();

    $this->withUnencryptedCookie(COOKIE_NAME, TestJwtSigner::sign(['exp' => time() - 3600]))
        ->get('/_auth/probe-web')
        ->assertStatus(401);
});

it('prefers the bearer token over a stale session cookie', function (): void {
    seedOperator();
    $other = TestJwtSigner::SUB === 'other-subject' ? 'another-subject' : 'other-subject';
    User::create([
        'name' => 'Other',
        'email' => 'other@diffops.test',
        'password' => 'secret',
        'supabase_uid' => $other,
    ]);

    $this->withUnencryptedCookie(COOKIE_NAME, 'not-a-jwt')
        ->getJson('/_auth/probe-web', ['Authorization' => 'Bearer '.TestJwtSigner::sign(['sub' => $other])])
        ->assertOk()
        ->assertJson(['supabase_uid' => $other]);
});
