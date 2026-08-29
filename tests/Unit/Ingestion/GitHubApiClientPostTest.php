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

function postClient(?GitHubAppTokenService $tokens = null): GitHubApiClient
{
    return new GitHubApiClient($tokens ?? postTokens());
}

function postTokens(): GitHubAppTokenService
{
    $tokens = Mockery::mock(GitHubAppTokenService::class);
    $tokens->shouldReceive('tokenForInstallation')->andReturn('fake-install-token');

    return $tokens;
}

it('posts json with the installation bearer and github api headers', function (): void {
    Http::fake(['*/repos/*/*/issues/*/comments' => Http::response(['id' => 123456], 201)]);

    $result = postClient()->post('/repos/owner/repo/issues/7/comments', ['body' => 'Recon Report'], 42);

    expect($result['id'])->toBe(123456);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.github.com/repos/owner/repo/issues/7/comments'
        && $request->method() === 'POST'
        && $request->hasHeader('Authorization', 'Bearer fake-install-token')
        && $request->hasHeader('Accept', 'application/vnd.github+json')
        && $request->hasHeader('X-GitHub-Api-Version', '2022-11-28')
        && $request->hasHeader('User-Agent', 'DiffOps')
        && str_contains($request->body(), 'Recon Report'));
});

it('retries a 429 response and succeeds on the next attempt', function (): void {
    Http::fake(['*/repos/*/*/issues/*/comments' => Http::sequence()
        ->push('rate limited', 429)
        ->push(['id' => 99], 201)]);

    $result = postClient()->post('/repos/owner/repo/issues/7/comments', ['body' => 'x'], 42);

    expect($result['id'])->toBe(99);

    Http::assertSentCount(2);
});

it('throws on a 4xx error response', function (): void {
    Http::fake(['*/repos/*/*/issues/*/comments' => Http::response(['message' => 'Validation Failed'], 422)]);

    expect(fn () => postClient()->post('/repos/owner/repo/issues/7/comments', ['body' => 'x'], 42))
        ->toThrow(HttpClientException::class);

    Http::assertSentCount(1);
});
