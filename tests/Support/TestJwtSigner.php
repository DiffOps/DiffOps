<?php

namespace Tests\Support;

use Firebase\JWT\JWT;

/**
 * Signs Supabase-shaped JWT fixtures for the auth test suites.
 *
 * Pure static class — no global functions, autoloaded via the Tests\ PSR-4
 * mapping (tests/Support/TestJwtSigner.php).
 */
final class TestJwtSigner
{
    /**
     * Shared fixture secret. Long enough for HS512 (>= 64 bytes) so the
     * "HS512 is forbidden" test can sign a valid token in the first place.
     */
    public const SECRET = 'diffops-test-jwt-secret-0123456789abcdef0123456789abcdef0123456789abcdef';

    public const SUB = '8f14e45f-ceea-4a3e-9b7c-1a2b3c4d5e6f';

    public const ISSUER_BASE = 'https://qkrsrfrlwclzloqjisdr.supabase.co';

    public const ISSUER = 'https://qkrsrfrlwclzloqjisdr.supabase.co/auth/v1';

    public const AUDIENCE = 'authenticated';

    /**
     * The default claim set emitted by Supabase Cloud.
     *
     * @return array<string, int|string>
     */
    public static function defaults(): array
    {
        $now = time();

        return [
            'sub' => self::SUB,
            'iss' => self::ISSUER,
            'aud' => self::AUDIENCE,
            'iat' => $now,
            'exp' => $now + 3600,
        ];
    }

    /**
     * Sign a token with the given claim overrides.
     *
     * @param  array<string, int|string|null>  $overrides
     */
    public static function sign(array $overrides = [], string $alg = 'HS256', string $key = self::SECRET): string
    {
        return JWT::encode(array_merge(self::defaults(), $overrides), $key, $alg);
    }

    /**
     * Craft a token with an "alg: none" header (empty signature).
     *
     * @param  array<string, int|string|null>  $overrides
     */
    public static function noneToken(array $overrides = []): string
    {
        $header = self::urlsafeB64(json_encode(['typ' => 'JWT', 'alg' => 'none']));
        $payload = self::urlsafeB64(json_encode(array_merge(self::defaults(), $overrides)));

        return $header.'.'.$payload.'.';
    }

    /**
     * A token that cannot even be parsed as a JWT.
     */
    public static function malformed(): string
    {
        return 'not-a-valid-jwt';
    }

    private static function urlsafeB64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
