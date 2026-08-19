<?php

namespace App\Services;

use Firebase\JWT\JWK;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;
use UnexpectedValueException;

/**
 * Fetches, parses and caches the Supabase JWK Set (RS256 public keys).
 *
 * The key set is cached under a single key with a configurable TTL; unknown
 * kids trigger exactly one refetch (key rotation) and failures are never
 * cached, so a broken JWKS endpoint fails closed instead of serving stale
 * keys forever.
 */
class SupabaseJwksService
{
    private const CACHE_KEY = 'supabase:jwks';

    /**
     * The parsed JWK Set, keyed by kid.
     *
     * @return array<string, Key>
     *
     * @throws UnexpectedValueException when the fetch or the payload fails
     */
    public function keys(): array
    {
        if (Cache::has(self::CACHE_KEY)) {
            return Cache::get(self::CACHE_KEY);
        }

        $url = config('services.supabase.jwks_url');

        if (! is_string($url) || $url === '') {
            throw new UnexpectedValueException('Supabase JWKS URL is not configured.');
        }

        $response = Http::timeout((int) config('services.supabase.jwks_timeout', 5))
            ->get($url);

        if ($response->failed()) {
            throw new UnexpectedValueException('Supabase JWKS request failed.');
        }

        try {
            $keys = JWK::parseKeySet($response->json() ?? [], 'RS256');
        } catch (Throwable $e) {
            throw new UnexpectedValueException('Supabase JWKS payload is invalid.', 0, $e);
        }

        Cache::put(self::CACHE_KEY, $keys, (int) config('services.supabase.jwks_cache_ttl', 3600));

        return $keys;
    }

    /**
     * Resolve the key for a kid, refetching exactly once on a miss.
     */
    public function keyForKid(?string $kid): ?Key
    {
        $keys = $this->keys();

        if ($kid === null || trim($kid) === '') {
            return count($keys) === 1 ? array_values($keys)[0] : null;
        }

        if (isset($keys[$kid])) {
            return $keys[$kid];
        }

        $this->invalidate();

        return $this->keys()[$kid] ?? null;
    }

    /**
     * Drop the cached key set so the next access refetches.
     */
    public function invalidate(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
