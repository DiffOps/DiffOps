<?php

declare(strict_types=1);

use App\Enums\PrFileStatus;
use App\Enums\PrState;
use App\Jobs\ProcessIncursionJob;
use App\Models\Organization;
use App\Models\PullRequest;
use App\Models\PullRequestFile;
use App\Models\Repository;
use App\Services\GitHub\DiffFetcher;
use App\Services\GitHub\GitHubApiClient;
use App\Services\GitHub\GitHubAppTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Support\GitHubWebhookFixtures;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $tokens = Mockery::mock(GitHubAppTokenService::class);
    $tokens->shouldReceive('tokenForInstallation')->andReturn('ghs_installation_token');

    app()->instance(DiffFetcher::class, new DiffFetcher(new GitHubApiClient($tokens)));
});

function prJobSeedOrgAndRepo(int $repoId = 424242, string $fullName = 'acme/web'): array
{
    $org = Organization::create(['name' => 'Acme', 'slug' => 'acme-'.Str::uuid()]);
    $repo = Repository::create([
        'organization_id' => $org->id,
        'github_repo_id' => $repoId,
        'full_name' => $fullName,
    ]);

    return [$org, $repo];
}

function prJobSeedPullRequest(array $overrides = []): PullRequest
{
    $org = Organization::create(['name' => 'Acme', 'slug' => 'acme-'.Str::uuid()]);

    Repository::create([
        'organization_id' => $org->id,
        'github_repo_id' => 424242,
        'full_name' => 'acme/web',
    ]);

    return PullRequest::create(array_merge([
        'organization_id' => $org->id,
        'github_repo_id' => 424242,
        'repo_full_name' => 'acme/web',
        'github_pr_number' => 42,
        'title' => 'Add rate limiting to the api',
        'author_username' => 'devacme',
        'author_avatar_url' => 'https://avatars.example.com/devacme.png',
        'base_ref' => 'main',
        'head_ref' => 'feat/rate-limit',
        'head_sha' => str_repeat('a', 40),
        'state' => 'open',
        'is_draft' => false,
    ], $overrides));
}

it('does nothing without a registered repository', function (): void {
    Http::fake();

    (new ProcessIncursionJob(GitHubWebhookFixtures::pullRequestOpened(), 'delivery-1'))->handle();

    expect(PullRequest::count())->toBe(0);

    Http::assertNothingSent();
});

it('creates the pull request with the mapped fields on opened', function (): void {
    [$org] = prJobSeedOrgAndRepo();

    Http::fake(['*/repos/acme/web/pulls/42/files*' => Http::response([], 200)]);

    (new ProcessIncursionJob(GitHubWebhookFixtures::pullRequestOpened(), 'delivery-1'))->handle();

    $pr = PullRequest::first();

    expect($pr)->not->toBeNull()
        ->and($pr->organization_id)->toBe($org->id)
        ->and($pr->github_repo_id)->toBe(424242)
        ->and($pr->repo_full_name)->toBe('acme/web')
        ->and($pr->github_pr_number)->toBe(42)
        ->and($pr->title)->toBe('Add rate limiting to the api')
        ->and($pr->author_username)->toBe('devacme')
        ->and($pr->author_avatar_url)->toBe('https://avatars.example.com/devacme.png')
        ->and($pr->base_ref)->toBe('main')
        ->and($pr->head_ref)->toBe('feat/rate-limit')
        ->and($pr->head_sha)->toBe(str_repeat('a', 40))
        ->and($pr->state)->toBe(PrState::Open)
        ->and($pr->is_draft)->toBeFalse();
});

it('updates head sha and metadata on synchronize', function (): void {
    prJobSeedPullRequest(['head_sha' => str_repeat('a', 40)]);

    Http::fake(['*/repos/acme/web/pulls/42/files*' => Http::response([], 200)]);

    (new ProcessIncursionJob(GitHubWebhookFixtures::pullRequestSynchronize(), 'delivery-2'))->handle();

    $pr = PullRequest::first();

    expect($pr->head_sha)->toBe(str_repeat('c', 40))
        ->and($pr->state)->toBe(PrState::Open);
});

it('skips the fetch when the head sha is unchanged and files exist', function (): void {
    prJobSeedPullRequest(['head_sha' => str_repeat('c', 40)]);
    PullRequestFile::create(['pull_request_id' => PullRequest::first()->id, 'file_path' => 'app/A.php', 'status' => 'added']);

    Http::fake();

    (new ProcessIncursionJob(GitHubWebhookFixtures::pullRequestSynchronize(), 'delivery-3'))->handle();

    expect(PullRequest::count())->toBe(1);

    Http::assertNothingSent();
});

it('marks the pull request closed without a fetch when closed unmerged', function (): void {
    prJobSeedPullRequest(['state' => 'open']);

    Http::fake();

    (new ProcessIncursionJob(GitHubWebhookFixtures::pullRequestClosed(false), 'delivery-4'))->handle();

    $pr = PullRequest::first();

    expect($pr->state)->toBe(PrState::Closed)
        ->and($pr->closed_at)->not->toBeNull();

    Http::assertNothingSent();
});

it('marks the pull request merged when the event says merged', function (): void {
    prJobSeedPullRequest(['state' => 'open']);

    Http::fake();

    (new ProcessIncursionJob(GitHubWebhookFixtures::pullRequestClosed(true), 'delivery-5'))->handle();

    expect(PullRequest::first()->state)->toBe(PrState::Merged);

    Http::assertNothingSent();
});

it('reopens the pull request and fetches files when files are missing', function (): void {
    prJobSeedPullRequest(['state' => 'closed', 'head_sha' => str_repeat('a', 40)]);

    Http::fake(['*/repos/acme/web/pulls/42/files*' => Http::response([], 200)]);

    (new ProcessIncursionJob(GitHubWebhookFixtures::pullRequestReopened(), 'delivery-6'))->handle();

    expect(PullRequest::first()->state)->toBe(PrState::Open);

    Http::assertSentCount(1);
});

it('updates the title on edited without refetching files', function (): void {
    prJobSeedPullRequest(['state' => 'open', 'head_sha' => str_repeat('a', 40)]);
    PullRequestFile::create(['pull_request_id' => PullRequest::first()->id, 'file_path' => 'app/A.php', 'status' => 'added']);

    Http::fake();

    (new ProcessIncursionJob(GitHubWebhookFixtures::pullRequestEdited(), 'delivery-7'))->handle();

    expect(PullRequest::first()->title)->toBe('Add rate limiting to the api (edited)');

    Http::assertNothingSent();
});

it('processes the pull request for every registered organization', function (): void {
    prJobSeedOrgAndRepo();
    prJobSeedOrgAndRepo();

    Http::fake(['*/repos/acme/web/pulls/42/files*' => Http::response([], 200)]);

    (new ProcessIncursionJob(GitHubWebhookFixtures::pullRequestOpened(), 'delivery-8'))->handle();

    expect(PullRequest::count())->toBe(2);
});

function prJobDiffFile(string $path, string $status = 'modified', array $overrides = []): array
{
    return array_merge([
        'filename' => $path,
        'status' => $status,
        'additions' => 3,
        'deletions' => 1,
        'patch' => "@@ -1,3 +1,4 @@\n+new",
    ], $overrides);
}

it('persists the normalized files of the pull request', function (): void {
    prJobSeedOrgAndRepo();

    Http::fake(['*/repos/acme/web/pulls/42/files*' => Http::response([
        prJobDiffFile('app/A.php', 'added', ['additions' => 5, 'deletions' => 0]),
        prJobDiffFile('app/B.php', 'modified', ['additions' => 2, 'deletions' => 1]),
    ], 200)]);

    (new ProcessIncursionJob(GitHubWebhookFixtures::pullRequestOpened(), 'delivery-9'))->handle();

    $fileA = PullRequestFile::where('file_path', 'app/A.php')->first();
    $fileB = PullRequestFile::where('file_path', 'app/B.php')->first();

    expect($fileA->status)->toBe(PrFileStatus::Added)
        ->and($fileA->additions)->toBe(5)
        ->and($fileA->deletions)->toBe(0)
        ->and($fileB->status)->toBe(PrFileStatus::Modified)
        ->and($fileB->additions)->toBe(2)
        ->and($fileB->deletions)->toBe(1);
});

it('flags files without a patch as binary', function (): void {
    prJobSeedOrgAndRepo();

    Http::fake(['*/repos/acme/web/pulls/42/files*' => Http::response([
        prJobDiffFile('assets/logo.png', 'added', ['patch' => null]),
    ], 200)]);

    (new ProcessIncursionJob(GitHubWebhookFixtures::pullRequestOpened(), 'delivery-10'))->handle();

    $file = PullRequestFile::where('file_path', 'assets/logo.png')->first();

    expect($file->is_binary)->toBeTrue()
        ->and($file->raw_patch)->toBeNull()
        ->and($file->bytes)->toBe(0);
});

it('stores the patch bytes as the raw patch length', function (): void {
    prJobSeedOrgAndRepo();

    $patch = "@@ -1,3 +1,4 @@\n+secret line";

    Http::fake(['*/repos/acme/web/pulls/42/files*' => Http::response([
        prJobDiffFile('config/app.php', 'modified', ['patch' => $patch]),
    ], 200)]);

    (new ProcessIncursionJob(GitHubWebhookFixtures::pullRequestOpened(), 'delivery-11'))->handle();

    $file = PullRequestFile::where('file_path', 'config/app.php')->first();

    expect($file->raw_patch)->toBe($patch)
        ->and($file->bytes)->toBe(strlen($patch))
        ->and($file->is_binary)->toBeFalse();
});

it('persists files fetched across two pages', function (): void {
    prJobSeedOrgAndRepo();

    Http::fake([
        '*/repos/acme/web/pulls/42/files*' => Http::sequence()
            ->push([prJobDiffFile('app/A.php')], 200, ['Link' => '<https://api.github.com/repos/acme/web/pulls/42/files?page=2>; rel="next"'])
            ->push([prJobDiffFile('app/B.php'), prJobDiffFile('app/C.php')], 200),
    ]);

    (new ProcessIncursionJob(GitHubWebhookFixtures::pullRequestOpened(), 'delivery-12'))->handle();

    expect(PullRequestFile::count())->toBe(3);
});

it('removes files that are no longer part of the diff on synchronize', function (): void {
    prJobSeedPullRequest(['state' => 'open', 'head_sha' => str_repeat('a', 40)]);
    PullRequestFile::create(['pull_request_id' => PullRequest::first()->id, 'file_path' => 'app/old.php', 'status' => 'added']);

    Http::fake(['*/repos/acme/web/pulls/42/files*' => Http::response([
        prJobDiffFile('app/new.php', 'added'),
    ], 200)]);

    (new ProcessIncursionJob(GitHubWebhookFixtures::pullRequestSynchronize(), 'delivery-13'))->handle();

    expect(PullRequestFile::where('file_path', 'app/old.php')->exists())->toBeFalse()
        ->and(PullRequestFile::where('file_path', 'app/new.php')->exists())->toBeTrue();
});

it('persists nothing when the file fetch fails', function (): void {
    prJobSeedOrgAndRepo();

    Http::fake(['*/repos/acme/web/pulls/42/files*' => Http::response('boom', 500)]);

    expect(fn () => (new ProcessIncursionJob(GitHubWebhookFixtures::pullRequestOpened(), 'delivery-14'))->handle())
        ->toThrow(RequestException::class);

    expect(PullRequest::count())->toBe(0);
});
