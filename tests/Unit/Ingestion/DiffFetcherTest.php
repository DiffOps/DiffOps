<?php

declare(strict_types=1);

use App\Services\GitHub\DiffFetcher;
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

function diffFetcher(): DiffFetcher
{
    $tokens = Mockery::mock(GitHubAppTokenService::class);
    $tokens->shouldReceive('tokenForInstallation')->andReturn('ghs_installation_token');

    return new DiffFetcher(new GitHubApiClient($tokens));
}

function diffFile(string $path, string $status = 'modified', array $overrides = []): array
{
    return array_merge([
        'filename' => $path,
        'status' => $status,
        'additions' => 3,
        'deletions' => 1,
        'patch' => "@@ -1,3 +1,4 @@\n+new",
    ], $overrides);
}

it('fetches the pull request metadata', function (): void {
    Http::fake([
        '*/repos/acme/web/pulls/7' => Http::response(['number' => 7, 'title' => 'Fix login'], 200),
    ]);

    $pull = diffFetcher()->pull('acme', 'web', 7, 987654);

    expect($pull)->toBe(['number' => 7, 'title' => 'Fix login']);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.github.com/repos/acme/web/pulls/7');
});

it('fetches the files of the first page', function (): void {
    Http::fake([
        '*/repos/acme/web/pulls/7/files*' => Http::response([diffFile('app/A.php')], 200),
    ]);

    $files = diffFetcher()->files('acme', 'web', 7, 987654);

    expect($files)->toHaveCount(1)
        ->and($files[0]['filename'])->toBe('app/A.php');

    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://api.github.com/repos/acme/web/pulls/7/files'));
});

it('follows the Link rel next header across two pages', function (): void {
    Http::fake([
        '*/repos/acme/web/pulls/7/files*' => Http::sequence()
            ->push([diffFile('app/A.php')], 200, ['Link' => '<https://api.github.com/repos/acme/web/pulls/7/files?page=2>; rel="next"'])
            ->push([diffFile('app/B.php'), diffFile('app/C.php')], 200),
    ]);

    $files = diffFetcher()->files('acme', 'web', 7, 987654);

    expect($files)->toHaveCount(3)
        ->and(array_column($files, 'filename'))->toBe(['app/A.php', 'app/B.php', 'app/C.php']);

    Http::assertSentCount(2);
});

it('stops after the last page when there is no Link next header', function (): void {
    Http::fake([
        '*/repos/acme/web/pulls/7/files*' => Http::response([diffFile('app/A.php')], 200),
    ]);

    $files = diffFetcher()->files('acme', 'web', 7, 987654);

    expect($files)->toHaveCount(1);

    Http::assertSentCount(1);
});

it('propagates a 404 from the pull request files endpoint', function (): void {
    Http::fake([
        '*/repos/acme/web/pulls/7/files*' => Http::response('not found', 404),
    ]);

    expect(fn () => diffFetcher()->files('acme', 'web', 7, 987654))
        ->toThrow(HttpClientException::class);
});
