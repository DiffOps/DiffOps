<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestJwtSigner;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('services.supabase', [
        'url' => TestJwtSigner::ISSUER_BASE,
        'jwt_secret' => TestJwtSigner::SECRET,
        'jwt_audience' => TestJwtSigner::AUDIENCE,
        'jwt_clock_skew' => 30,
    ]);
});

function sessionCookie($response)
{
    // Symfony 7 expõe os campos de Cookie apenas via getters — firstWhere('name')
    // cairia em data_get sobre propriedade privada e sempre retornaria null.
    return collect($response->headers->getCookies())
        ->first(fn ($cookie) => $cookie->getName() === 'diffops_session');
}

it('stores the session cookie for a valid access token', function (): void {
    User::factory()->create(['supabase_uid' => TestJwtSigner::SUB]);

    $response = $this->postJson('/api/auth/session', [
        'token' => TestJwtSigner::sign(),
    ]);

    $response->assertNoContent();

    $cookie = sessionCookie($response);

    expect($cookie)->not->toBeNull()
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->getSameSite())->toBe('lax')
        ->and(strlen((string) $cookie->getValue()))->toBeGreaterThan(100);
});

it('rejects an invalid access token', function (): void {
    $this->postJson('/api/auth/session', ['token' => 'not-a-jwt'])
        ->assertStatus(401)
        ->assertJson(['message' => 'Unauthenticated.']);
});

it('requires a token', function (): void {
    $this->postJson('/api/auth/session')->assertStatus(401);
});

it('clears the session cookie on destroy', function (): void {
    $response = $this->deleteJson('/api/auth/session');

    $response->assertNoContent();

    $cookie = sessionCookie($response);

    expect($cookie)->not->toBeNull()
        ->and($cookie->getMaxAge())->toBeLessThanOrEqual(0);
});
