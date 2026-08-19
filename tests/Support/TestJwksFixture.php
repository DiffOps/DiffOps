<?php

namespace Tests\Support;

use Firebase\JWT\JWT;

/**
 * Signs RS256 JWT fixtures backed by a per-instance RSA-2048 keypair, so the
 * auth suites can exercise JWKS key rotation (two instances = two distinct
 * keypairs).
 *
 * Pure class, autoloaded via the Tests\ PSR-4 mapping.
 */
final class TestJwksFixture
{
    private ?string $privatePem = null;

    public function __construct(private readonly string $kid = 'test-kid-1') {}

    public static function make(string $kid = 'test-kid-1'): self
    {
        return new self($kid);
    }

    public function kid(): string
    {
        return $this->kid;
    }

    public function privatePem(): string
    {
        if ($this->privatePem === null) {
            $key = openssl_pkey_new([
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ]);

            openssl_pkey_export($key, $pem);

            $this->privatePem = $pem;
        }

        return $this->privatePem;
    }

    public function publicPem(): string
    {
        $details = openssl_pkey_get_details(openssl_pkey_get_private($this->privatePem()));

        return $details['key'];
    }

    /**
     * Sign a Supabase-shaped token with this fixture's RSA key.
     *
     * @param  array<string, int|string|null>  $overrides
     */
    public function sign(array $overrides = []): string
    {
        return JWT::encode(
            array_merge(TestJwtSigner::defaults(), $overrides),
            $this->privatePem(),
            'RS256',
            $this->kid(),
        );
    }

    /**
     * The JWK Set payload advertising this fixture's public key.
     *
     * @return array{keys: list<array<string, string>>}
     */
    public function jwksPayload(string $alg = 'RS256'): array
    {
        $details = openssl_pkey_get_details(openssl_pkey_get_private($this->privatePem()));

        return [
            'keys' => [[
                'kty' => 'RSA',
                'use' => 'sig',
                'alg' => $alg,
                'kid' => $this->kid(),
                'n' => JWT::urlsafeB64Encode($details['rsa']['n']),
                'e' => JWT::urlsafeB64Encode($details['rsa']['e']),
            ]],
        ];
    }
}
