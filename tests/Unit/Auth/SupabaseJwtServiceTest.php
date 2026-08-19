<?php

use App\Services\SupabaseJwtService;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Tests\Support\TestJwtSigner;

beforeEach(function () {
    config()->set('services.supabase', [
        'url' => TestJwtSigner::ISSUER_BASE,
        'jwt_secret' => TestJwtSigner::SECRET,
        'jwt_audience' => TestJwtSigner::AUDIENCE,
        'jwt_clock_skew' => 30,
    ]);
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
