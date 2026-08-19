<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

/**
 * Fetches and normalizes the Supabase user profile (GoTrue /auth/v1/user)
 * for an authenticated subject.
 *
 * The HTTP sync is opt-in: with profile_sync_http disabled the fetcher
 * returns an empty profile without touching the network. Responses are
 * cached per token hash (the raw token is never stored) with a configurable
 * TTL, and failures are never cached so a broken endpoint does not poison
 * the profile for the whole TTL window. The payload subject must match the
 * expected token subject or the profile is rejected (fail-closed).
 */
class SupabaseProfileFetcher
{
    private const CACHE_PREFIX = 'supabase:profile:';

    /**
     * @return array{name: string|null, email: string|null, github_username: string|null, avatar_url: string|null}
     *
     * @throws UnexpectedValueException when the fetch or the payload fails
     */
    public function fetch(string $token, string $expectedSub): array
    {
        if (! (bool) config('services.supabase.profile_sync_http', false)) {
            return [];
        }

        $url = config('services.supabase.profile_sync_url');

        if (! is_string($url) || $url === '') {
            throw new UnexpectedValueException('Supabase profile sync URL is not configured.');
        }

        $cacheKey = self::CACHE_PREFIX.hash('sha256', $token);

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $response = Http::timeout((int) config('services.supabase.profile_sync_timeout', 5))
            ->withToken($token)
            ->get($url);

        if ($response->failed()) {
            throw new UnexpectedValueException('Supabase profile request failed.');
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new UnexpectedValueException('Supabase profile payload is invalid.');
        }

        if (($payload['id'] ?? null) !== $expectedSub) {
            throw new UnexpectedValueException('Supabase profile subject does not match the token.');
        }

        $profile = $this->normalize($payload);

        Cache::put($cacheKey, $profile, (int) config('services.supabase.profile_sync_cache_ttl', 300));

        return $profile;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{name: string|null, email: string|null, github_username: string|null, avatar_url: string|null}
     */
    private function normalize(array $payload): array
    {
        $metadata = (array) ($payload['user_metadata'] ?? []);
        $identity = $this->githubIdentity($payload);

        return [
            'name' => $metadata['full_name'] ?? $metadata['name'] ?? $identity['identity_data']['name'] ?? null,
            'email' => $payload['email'] ?? $identity['email'] ?? null,
            'github_username' => $identity['identity_data']['user_name'] ?? $metadata['user_name'] ?? $payload['preferred_username'] ?? null,
            'avatar_url' => $identity['identity_data']['avatar_url'] ?? $metadata['avatar_url'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function githubIdentity(array $payload): array
    {
        $identities = $payload['identities'] ?? [];

        if (! is_array($identities)) {
            return [];
        }

        foreach ($identities as $identity) {
            if (is_array($identity) && ($identity['provider'] ?? null) === 'github') {
                return $identity;
            }
        }

        return [];
    }
}
