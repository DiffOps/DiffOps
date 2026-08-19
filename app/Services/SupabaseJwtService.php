<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use UnexpectedValueException;

class SupabaseJwtService
{
    /**
     * Decode and validate a Supabase access token signed with HS256.
     *
     * The token must be cryptographically valid (HS256 only, with the
     * configured clock skew) and carry an issuer derived from the Supabase
     * URL, the "authenticated" audience and a non-empty subject.
     *
     * @return array<string, mixed> the decoded claims
     *
     * @throws UnexpectedValueException when the token is invalid
     */
    public function decode(string $token): array
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

        return (array) $payload;
    }
}
