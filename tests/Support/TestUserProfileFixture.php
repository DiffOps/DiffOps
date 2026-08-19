<?php

namespace Tests\Support;

/**
 * Builds the user profile payload returned by the Supabase GoTrue
 * /auth/v1/user endpoint (GET with a Bearer access token).
 *
 * The shape mirrors the real GoTrue response: top-level profile fields
 * (id, email, phone, confirmed_at, last_sign_in_at), app_metadata
 * (provider), user_metadata (full_name, user_name, avatar_url) and the
 * identities array carrying the linked GitHub identity with its own
 * identity_data.
 *
 * Pure static class — no global functions, autoloaded via the Tests\
 * PSR-4 mapping (tests/Support/TestUserProfileFixture.php).
 */
final class TestUserProfileFixture
{
    public const EMAIL = 'operator@diffops.test';

    public const GITHUB_USERNAME = 'op-one';

    public const AVATAR = 'https://avatars.example.com/op-one.png';

    public const PHONE = '+55 11 99999-0000';

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function payload(array $overrides = []): array
    {
        return array_merge([
            'id' => TestJwtSigner::SUB,
            'aud' => 'authenticated',
            'email' => self::EMAIL,
            'email_confirmed_at' => '2026-01-01T00:00:00.000Z',
            'phone' => self::PHONE,
            'confirmed_at' => '2026-01-01T00:00:00.000Z',
            'last_sign_in_at' => '2026-01-01T00:00:00.000Z',
            'app_metadata' => ['provider' => 'github'],
            'user_metadata' => [
                'full_name' => 'Operator One',
                'user_name' => self::GITHUB_USERNAME,
                'avatar_url' => self::AVATAR,
            ],
            'identities' => [[
                'provider' => 'github',
                'identity_data' => [
                    'sub' => 'github-12345',
                    'email' => self::EMAIL,
                    'email_verified' => true,
                    'name' => 'Operator One',
                    'user_name' => self::GITHUB_USERNAME,
                    'avatar_url' => self::AVATAR,
                ],
                'email' => self::EMAIL,
            ]],
            'created_at' => '2026-01-01T00:00:00.000Z',
            'updated_at' => '2026-01-01T00:00:00.000Z',
        ], $overrides);
    }
}
