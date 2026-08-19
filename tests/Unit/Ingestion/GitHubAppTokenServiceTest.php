<?php

declare(strict_types=1);

use App\Services\GitHub\GitHubAppTokenService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $keyPair = githubAppTokenKeyPair();

    config()->set('services.github', [
        'api_url' => 'https://api.github.com',
        'app_id' => 123456,
        'app_private_key' => $keyPair['private'],
        'webhook_secret' => 'the-webhook-secret',
        'timeout' => 15,
        'token_cache_ttl' => 3300,
        'retries' => 2,
    ]);

    Cache::flush();
});

function githubAppTokenKeyPair(): array
{
    static $pair = null;

    if ($pair !== null) {
        return $pair;
    }

    $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);

    openssl_pkey_export($resource, $privateKey);

    $details = openssl_pkey_get_details($resource);

    return $pair = [
        'private' => $privateKey,
        'public' => $details['key'],
    ];
}

function githubAppTokenService(): GitHubAppTokenService
{
    return new GitHubAppTokenService;
}

function githubTokenEndpoint(): string
{
    return 'https://api.github.com/app/installations/987654/access_tokens';
}

it('signs the app jwt with the app id and a short exp', function (): void {
    Http::fake([githubTokenEndpoint() => Http::response(['token' => 'ghs_test_token'], 201)]);

    $capturedJwt = null;

    Http::fake(['*' => function ($request) use (&$capturedJwt) {
        $capturedJwt = substr((string) $request->header('Authorization')[0], strlen('Bearer '));

        return Http::response(['token' => 'ghs_test_token'], 201);
    }]);

    githubAppTokenService()->tokenForInstallation(987654);

    $claims = JWT::decode($capturedJwt, new Key(githubAppTokenKeyPair()['public'], 'RS256'));

    expect($claims->iss)->toBe(123456)
        ->and($claims->exp - $claims->iat)->toBeLessThanOrEqual(600);
});

it('exchanges the jwt for an installation token via the access token endpoint', function (): void {
    Http::fake([githubTokenEndpoint() => Http::response(['token' => 'ghs_test_token'], 201)]);

    $token = githubAppTokenService()->tokenForInstallation(987654);

    expect($token)->toBe('ghs_test_token');

    Http::assertSent(fn (Request $request): bool => $request->url() === githubTokenEndpoint()
        && $request->hasHeader('Authorization')
        && str_starts_with((string) $request->header('Authorization')[0], 'Bearer '));
});

it('reuses the cached installation token without a second request', function (): void {
    Http::fake([githubTokenEndpoint() => Http::response(['token' => 'ghs_test_token'], 201)]);

    $service = githubAppTokenService();

    $service->tokenForInstallation(987654);
    $service->tokenForInstallation(987654);

    Http::assertSentCount(1);
});

it('refetches the installation token after the cache expires', function (): void {
    config()->set('services.github.token_cache_ttl', 0);

    Http::fake([githubTokenEndpoint() => Http::response(['token' => 'ghs_test_token'], 201)]);

    $service = githubAppTokenService();

    $service->tokenForInstallation(987654);
    $service->tokenForInstallation(987654);

    Http::assertSentCount(2);
});

it('fails closed without configured app credentials', function (): void {
    config()->set('services.github', [
        'api_url' => 'https://api.github.com',
        'app_id' => null,
        'app_private_key' => null,
        'webhook_secret' => null,
        'timeout' => 15,
        'token_cache_ttl' => 3300,
        'retries' => 2,
    ]);

    Http::fake();

    expect(fn () => githubAppTokenService()->tokenForInstallation(987654))
        ->toThrow(UnexpectedValueException::class);

    Http::assertNothingSent();
});

it('throws when the exchange response has no token', function (): void {
    Http::fake([githubTokenEndpoint() => Http::response(['message' => 'no token'], 201)]);

    expect(fn () => githubAppTokenService()->tokenForInstallation(987654))
        ->toThrow(UnexpectedValueException::class);
});

it('throws when the access token endpoint rejects the app jwt', function (): void {
    Http::fake([githubTokenEndpoint() => Http::response('unauthorized', 401)]);

    expect(fn () => githubAppTokenService()->tokenForInstallation(987654))
        ->toThrow(UnexpectedValueException::class);
});
