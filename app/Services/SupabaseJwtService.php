<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use UnexpectedValueException;

class SupabaseJwtService
{
    private ?SupabaseJwksService $jwks = null;

    public function __construct(?SupabaseJwksService $jwks = null)
    {
        $this->jwks = $jwks;
    }

    /**
     * Decode and validate a Supabase access token.
     *
     * HS256 tokens are verified against the shared secret (the legacy path);
     * RS256 tokens are verified against the public keys advertised by the
     * Supabase JWK Set. Both paths enforce the issuer derived from the
     * Supabase URL, the "authenticated" audience and a non-empty subject,
     * with the configured clock skew.
     *
     * @return array<string, mixed> the decoded claims
     *
     * @throws UnexpectedValueException when the token is invalid
     */
    public function decode(string $token): array
    {
        $header = $this->readHeader($token);

        return match ($header['alg'] ?? '') {
            'HS256' => $this->decodeHs256($token),
            'RS256' => $this->decodeRs256($token, $header['kid'] ?? null),
            default => throw new UnexpectedValueException('Supabase JWT uses an unsupported algorithm.'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function readHeader(string $token): array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new UnexpectedValueException('Supabase JWT is malformed.');
        }

        $header = json_decode(JWT::urlsafeB64Decode($parts[0]), true);

        if (! is_array($header)) {
            throw new UnexpectedValueException('Supabase JWT header is invalid.');
        }

        return $header;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeHs256(string $token): array
    {
        $secret = config('services.supabase.jwt_secret');

        if (! is_string($secret) || $secret === '') {
            throw new UnexpectedValueException('Supabase JWT secret is not configured.');
        }

        JWT::$leeway = (int) config('services.supabase.jwt_clock_skew', 30);

        try {
            $payload = JWT::decode($token, new Key($secret, 'HS256'));
        } finally {
            JWT::$leeway = 0;
        }

        return $this->validateClaims($payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeRs256(string $token, ?string $kid): array
    {
        JWT::$leeway = (int) config('services.supabase.jwt_clock_skew', 30);

        try {
            $key = $this->jwks()->keyForKid($kid);

            if ($key === null) {
                throw new UnexpectedValueException('Supabase JWT key is not available.');
            }

            $payload = JWT::decode($token, $key);
        } finally {
            JWT::$leeway = 0;
        }

        return $this->validateClaims($payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateClaims(object $payload): array
    {
        $issuer = config('services.supabase.jwt_issuer')
            ?: rtrim((string) config('services.supabase.url'), '/').'/auth/v1';

        if (! isset($payload->iss) || ! str_starts_with((string) $payload->iss, (string) $issuer)) {
            throw new UnexpectedValueException('Supabase JWT has an invalid issuer.');
        }

        if (! isset($payload->aud) || $payload->aud !== config('services.supabase.jwt_audience', 'authenticated')) {
            throw new UnexpectedValueException('Supabase JWT has an invalid audience.');
        }

        if (! isset($payload->sub) || trim((string) $payload->sub) === '') {
            throw new UnexpectedValueException('Supabase JWT has no subject.');
        }

        // Normalize nested objects (user_metadata, app_metadata) to arrays.
        return json_decode(json_encode($payload), true);
    }

    private function jwks(): SupabaseJwksService
    {
        return $this->jwks ??= app(SupabaseJwksService::class);
    }
}
