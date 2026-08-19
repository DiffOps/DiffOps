<?php

use App\Services\SupabaseJwtService;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Support\TestJwksFixture;
use Tests\Support\TestJwtSigner;

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

    Cache::forget('supabase:jwks');
});

it('decodes a valid HS256 token', function () {
    $claims = (new SupabaseJwtService)->decode(TestJwtSigner::sign());

    expect($claims['sub'])->toBe(TestJwtSigner::SUB)
        ->and($claims['iss'])->toBe(TestJwtSigner::ISSUER)
        ->and($claims['aud'])->toBe(TestJwtSigner::AUDIENCE);
});

it('rejects a token expired beyond the clock skew', function () {
    (new SupabaseJwtService)->decode(TestJwtSigner::sign(['exp' => time() - 3600]));
})->throws(ExpiredException::class);

it('accepts a token expired within the clock skew', function () {
    $claims = (new SupabaseJwtService)->decode(TestJwtSigner::sign(['exp' => time() - 10]));

    expect($claims['sub'])->toBe(TestJwtSigner::SUB);
});

it('rejects a token with a foreign issuer', function () {
    (new SupabaseJwtService)->decode(TestJwtSigner::sign(['iss' => 'https://evil.example.com/auth/v1']));
})->throws(UnexpectedValueException::class);

it('rejects a token with a foreign audience', function () {
    (new SupabaseJwtService)->decode(TestJwtSigner::sign(['aud' => 'service_role']));
})->throws(UnexpectedValueException::class);

it('rejects a token without a subject', function () {
    (new SupabaseJwtService)->decode(TestJwtSigner::sign(['sub' => null]));
})->throws(UnexpectedValueException::class);

it('rejects a token signed with another key', function () {
    (new SupabaseJwtService)->decode(TestJwtSigner::sign(key: 'another-diffops-test-secret-0123456789abcdef0123456789abcdef0123456789abcdef'));
})->throws(SignatureInvalidException::class);

it('rejects a malformed token', function () {
    (new SupabaseJwtService)->decode(TestJwtSigner::malformed());
})->throws(UnexpectedValueException::class);

it('rejects a token with alg none', function () {
    (new SupabaseJwtService)->decode(TestJwtSigner::noneToken());
})->throws(UnexpectedValueException::class);

it('rejects a token signed with HS512', function () {
    (new SupabaseJwtService)->decode(TestJwtSigner::sign(alg: 'HS512'));
})->throws(UnexpectedValueException::class);

it('fails fast when the jwt secret is not configured', function () {
    config()->set('services.supabase.jwt_secret', null);

    (new SupabaseJwtService)->decode(TestJwtSigner::sign());
})->throws(UnexpectedValueException::class);

it('decodes a valid RS256 token through the jwks', function () {
    $fixture = TestJwksFixture::make();

    Http::fake(['*/auth/v1/.well-known/jwks.json' => Http::response($fixture->jwksPayload())]);

    $claims = app(SupabaseJwtService::class)->decode($fixture->sign());

    expect($claims['sub'])->toBe(TestJwtSigner::SUB)
        ->and($claims['iss'])->toBe(TestJwtSigner::ISSUER)
        ->and($claims['aud'])->toBe(TestJwtSigner::AUDIENCE);
});

it('keeps HS256 on the secret path without touching the network', function () {
    Http::fake();

    $claims = app(SupabaseJwtService::class)->decode(TestJwtSigner::sign());

    Http::assertNothingSent();
    expect($claims['sub'])->toBe(TestJwtSigner::SUB);
});

it('keeps HS256 working even when the jwks endpoint is unavailable', function () {
    Http::fake(['*/auth/v1/.well-known/jwks.json' => Http::response('', 404)]);

    $claims = app(SupabaseJwtService::class)->decode(TestJwtSigner::sign());

    Http::assertNothingSent();
    expect($claims['sub'])->toBe(TestJwtSigner::SUB);
});

it('rejects an RS256 token whose kid is not advertised', function () {
    $fixture = TestJwksFixture::make('server-kid');

    Http::fake(['*/auth/v1/.well-known/jwks.json' => Http::response($fixture->jwksPayload())]);

    app(SupabaseJwtService::class)->decode(TestJwksFixture::make('evil-kid')->sign());
})->throws(UnexpectedValueException::class);

it('fails closed when the jwks endpoint errors', function () {
    Http::fake(['*/auth/v1/.well-known/jwks.json' => Http::response('', 500)]);

    app(SupabaseJwtService::class)->decode(TestJwksFixture::make()->sign());
})->throws(UnexpectedValueException::class);

it('rejects HS512 even when the jwks is reachable', function () {
    Http::fake(['*/auth/v1/.well-known/jwks.json' => Http::response(TestJwksFixture::make()->jwksPayload())]);

    app(SupabaseJwtService::class)->decode(TestJwtSigner::sign(alg: 'HS512'));
})->throws(UnexpectedValueException::class);

it('validates issuer, audience and subject after RS256 verification', function () {
    $fixture = TestJwksFixture::make();

    Http::fake(['*/auth/v1/.well-known/jwks.json' => Http::response($fixture->jwksPayload())]);

    expect(fn () => app(SupabaseJwtService::class)->decode($fixture->sign(['iss' => 'https://evil.example.com/auth/v1'])))
        ->toThrow(UnexpectedValueException::class);

    expect(fn () => app(SupabaseJwtService::class)->decode($fixture->sign(['aud' => 'service_role'])))
        ->toThrow(UnexpectedValueException::class);

    expect(fn () => app(SupabaseJwtService::class)->decode($fixture->sign(['sub' => null])))
        ->toThrow(UnexpectedValueException::class);
});

it('rejects an RS256 token signed with the same kid but a wrong key', function () {
    $server = TestJwksFixture::make('same-kid');
    $forged = TestJwksFixture::make('same-kid');

    Http::fake(['*/auth/v1/.well-known/jwks.json' => Http::response($server->jwksPayload())]);

    app(SupabaseJwtService::class)->decode($forged->sign());
})->throws(SignatureInvalidException::class);
