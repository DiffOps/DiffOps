<?php

namespace App\Services\GitHub;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

class GitHubAppTokenService
{
    /**
     * Exchange the GitHub App JWT for an installation access token.
     *
     * The token is cached per installation (Redis TTL 3300s by default) so
     * a hot job loop does not hammer the exchange endpoint.
     */
    public function tokenForInstallation(int $installationId): string
    {
        $appId = config('services.github.app_id');
        $privateKey = config('services.github.app_private_key');

        if (! is_numeric($appId) || ! is_string($privateKey) || $privateKey === '') {
            throw new UnexpectedValueException('GitHub App credentials are not configured.');
        }

        return Cache::remember(
            "github:installation_token:{$installationId}",
            (int) config('services.github.token_cache_ttl', 3300),
            fn (): string => $this->exchange((int) $appId, $privateKey, $installationId),
        );
    }

    private function exchange(int $appId, string $privateKey, int $installationId): string
    {
        $now = time();

        $jwt = JWT::encode([
            'iat' => $now,
            'exp' => $now + 600,
            'iss' => $appId,
        ], $privateKey, 'RS256');

        $response = Http::timeout((int) config('services.github.timeout', 15))
            ->withToken($jwt)
            ->acceptJson()
            ->post(rtrim((string) config('services.github.api_url', 'https://api.github.com'), '/')."/app/installations/{$installationId}/access_tokens");

        $payload = $response->json();

        if ($response->failed() || ! is_array($payload) || ! isset($payload['token'])) {
            throw new UnexpectedValueException('GitHub installation token exchange failed.');
        }

        return (string) $payload['token'];
    }
}
