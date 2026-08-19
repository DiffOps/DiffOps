<?php

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\SignatureInvalidException;
use Tests\Support\TestJwksFixture;
use Tests\Support\TestJwtSigner;

it('sets the kid and the RS256 algorithm in the token header', function () {
    $token = TestJwksFixture::make('alpha-kid')->sign();

    $header = json_decode(JWT::urlsafeB64Decode(explode('.', $token)[0]), true);

    expect($header['kid'])->toBe('alpha-kid')
        ->and($header['alg'])->toBe('RS256');
});

it('parses the jwks payload and decodes a token with the public key', function () {
    $fixture = TestJwksFixture::make('alpha-kid');

    $keys = JWK::parseKeySet($fixture->jwksPayload(), 'RS256');

    $claims = JWT::decode($fixture->sign(['sub' => 'fixture-sub']), $keys['alpha-kid']);

    expect($claims->sub)->toBe('fixture-sub')
        ->and($claims->iss)->toBe(TestJwtSigner::ISSUER);
});

it('produces distinct keypairs across instances', function () {
    $alpha = TestJwksFixture::make('alpha-kid');
    $bravo = TestJwksFixture::make('bravo-kid');

    $bravoKeys = JWK::parseKeySet($bravo->jwksPayload(), 'RS256');

    JWT::decode($alpha->sign(), $bravoKeys['bravo-kid']);
})->throws(SignatureInvalidException::class);
