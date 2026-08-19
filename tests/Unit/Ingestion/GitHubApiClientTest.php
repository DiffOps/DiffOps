<?php

declare(strict_types=1);

use App\Services\GitHub\GitHubApiClient;
use App\Services\GitHub\GitHubAppTokenService;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config()->set('services.github', [
        'api_url' => 'https://api.github.com',
        'app_id' => 123456,
        'app_private_key' => 'a-private-key',
        'webhook_secret' => null,
        'timeout' => 15,
        'token_cache_ttl' => 3300,
        'retries' => 2,
    ]);
});

function githubApiClient(?GitHubAppTokenService $tokens = null): GitHubApiClient
{
    return new GitHubApiClient($tokens ?? githubApiClientTokens());
}

function githubApiClientTokens(): GitHubAppTokenService
{
    $tokens = Mockery::mock(GitHubAppTokenService::class);
    $tokens->shouldReceive('tokenForInstallation')->andReturn('ghs_installation_token');

    return $tokens;
}

function githubApiPath(): string
{
    return '/repos/acme/web/pulls/1';
}

it('sends the installation token and the github api headers', function (): void {
    Http::fake([githubApiPath() => Http::response(['number' => 1], 200)]);

    githubApiClient()->get(githubApiPath(), [], 987654);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.github.com'.githubApiPath()
        && $request->hasHeader('Authorization', 'Bearer ghs_installation_token')
        && $request->hasHeader('Accept', 'application/vnd.github+json')
        && $request->hasHeader('X-GitHub-Api-Version', '2022-11-28')
        && $request->hasHeader('User-Agent', 'DiffOps'));
});

it('retries a 429 response and succeeds on the second attempt', function (): void {
    Http::fake([githubApiPath() => Http::sequence()
        ->push('rate limited', 429)
        ->push(['number' => 1], 200)]);

    expect(githubApiClient()->get(githubApiPath(), [], 987654))->toBe(['number' => 1]);

    Http::assertSentCount(2);
});

it('retries a 500 response and succeeds on the second attempt', function (): void {
    Http::fake([githubApiPath() => Http::sequence()
        ->push('boom', 500)
        ->push(['number' => 1], 200)]);

    expect(githubApiClient()->get(githubApiPath(), [], 987654))->toBe(['number' => 1]);

    Http::assertSentCount(2);
});

it('throws after exhausting the retry budget on 429', function (): void {
    Http::fake([githubApiPath() => Http::response('rate limited', 429)]);

    expect(fn () => githubApiClient()->get(githubApiPath(), [], 987654))
        ->toThrow(HttpClientException::class);

    Http::assertSentCount(3);
});

it('applies the configured api url and timeout to the request', function (): void {
    $captured = [];

    Http::fake(['*' => function (Request $request, array $options) use (&$captured) {
        $captured = ['url' => $request->url(), 'timeout' => $options['timeout'] ?? null];

        return Http::response(['number' => 1], 200);
    }]);

    githubApiClient()->get(githubApiPath(), [], null);

    expect($captured['url'])->toBe('https://api.github.com'.githubApiPath())
        ->and($captured['timeout'])->toBe(15);
});

it('decodes the json payload of a successful response', function (): void {
    Http::fake([githubApiPath() => Http::response(['number' => 7, 'title' => 'Fix login'], 200)]);

    expect(githubApiClient()->get(githubApiPath(), [], 987654))->toBe([
        'number' => 7,
        'title' => 'Fix login',
    ]);
});
