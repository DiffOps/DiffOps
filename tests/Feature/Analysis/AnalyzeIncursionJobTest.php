<?php

declare(strict_types=1);

use App\Enums\AiDecisionValidity;
use App\Enums\PrState;
use App\Enums\Verdict;
use App\Jobs\AnalyzeIncursionJob;
use App\Models\AiDecision;
use App\Models\ContributorRisk;
use App\Models\Organization;
use App\Models\PullRequest;
use App\Models\PullRequestFile;
use App\Models\Repository;
use App\Models\RiskAssessment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Support\GitHubWebhookFixtures;

uses(RefreshDatabase::class);

const AI_JOB_SECRET_PATCH = "@@ -1 +1 @@\n+const AWS = 'AKIAIOSFODNN7EXAMPLE';\n";

function aiJobSeedOrgAndRepo(int $repoId = 424242): array
{
    $org = Organization::create(['name' => 'Acme', 'slug' => 'acme-'.Str::uuid()]);
    $repo = Repository::create([
        'organization_id' => $org->id,
        'github_repo_id' => $repoId,
        'full_name' => 'acme/web',
    ]);

    return [$org, $repo];
}

function aiJobSeedPullRequest(array $overrides = []): PullRequest
{
    [$org] = aiJobSeedOrgAndRepo();

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

function aiJobSeedFile(PullRequest $pr, array $overrides = []): PullRequestFile
{
    return PullRequestFile::create(array_merge([
        'pull_request_id' => $pr->id,
        'file_path' => 'app/Http/Controllers/UserController.php',
        'status' => 'modified',
        'additions' => 1,
        'deletions' => 0,
        'raw_patch' => "@@ -1 +1 @@\n+return 1;\n",
        'bytes' => 40,
        'is_binary' => false,
    ], $overrides));
}

function aiJobPayload(string $sha = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'): array
{
    return GitHubWebhookFixtures::pullRequestOpened([
        'number' => 42,
        'pull_request' => [
            'state' => 'open',
            'title' => 'Add rate limiting to the api',
            'user' => ['login' => 'devacme', 'avatar_url' => 'https://avatars.example.com/devacme.png'],
            'base' => ['ref' => 'main', 'sha' => str_repeat('b', 40)],
            'head' => ['ref' => 'feat/rate-limit', 'sha' => $sha],
            'draft' => false,
            'merged' => false,
            'closed_at' => null,
            'merged_at' => null,
        ],
    ]);
}

function aiJobValidJson(int $score = 80): string
{
    $content = json_encode([
        'verdict' => $score >= 70 ? 'hostile' : 'flagged',
        'threat_score' => $score,
        'defcon_level' => 3,
        'flags' => ['debug'],
        'findings' => [],
    ]);

    return json_encode(['choices' => [['message' => ['content' => $content]]]]);
}

it('does nothing without a registered repository', function (): void {
    Http::preventStrayRequests();

    (new AnalyzeIncursionJob(GitHubWebhookFixtures::pullRequestOpened(['repository' => ['id' => 999999]]), 'd1'))->handle();

    expect(RiskAssessment::count())->toBe(0)
        ->and(ContributorRisk::count())->toBe(0);
});

it('does nothing without a valid pull request number', function (): void {
    Http::preventStrayRequests();

    (new AnalyzeIncursionJob(GitHubWebhookFixtures::pullRequestOpened(['number' => 0]), 'd1'))->handle();

    expect(RiskAssessment::count())->toBe(0);
});

it('skips closed pull requests', function (): void {
    Http::preventStrayRequests();

    $pr = aiJobSeedPullRequest(['state' => PrState::Closed, 'head_sha' => str_repeat('a', 40)]);
    aiJobSeedFile($pr);

    (new AnalyzeIncursionJob(aiJobPayload(), 'd1'))->handle();

    expect(RiskAssessment::count())->toBe(0);
});

it('skips pull requests already assessed for the head sha', function (): void {
    Http::preventStrayRequests();

    $pr = aiJobSeedPullRequest(['head_sha' => str_repeat('a', 40)]);
    aiJobSeedFile($pr);

    RiskAssessment::create([
        'pull_request_id' => $pr->id,
        'head_sha' => str_repeat('a', 40),
        'verdict' => Verdict::Clear,
        'defcon_level' => 5,
        'security_score' => 0,
        'risk_level' => 'low',
        'compliance_checks' => [],
        'is_degraded' => true,
    ]);

    (new AnalyzeIncursionJob(aiJobPayload(), 'd1'))->handle();

    expect(RiskAssessment::count())->toBe(1);
});

it('returns early when the pull request has no stored files', function (): void {
    Http::preventStrayRequests();

    aiJobSeedPullRequest(['head_sha' => str_repeat('a', 40)]);

    (new AnalyzeIncursionJob(aiJobPayload(), 'd1'))->handle();

    expect(RiskAssessment::count())->toBe(0);
});

it('persists the sensitive flags found by the heuristic audit', function (): void {
    Http::preventStrayRequests();
    config()->set('services.openrouter.api_key', null);

    $pr = aiJobSeedPullRequest(['head_sha' => str_repeat('a', 40)]);
    aiJobSeedFile($pr, ['file_path' => '.env.production', 'raw_patch' => "@@ -1 +1 @@\n+DB_PASSWORD=super-secret\n"]);
    aiJobSeedFile($pr, ['file_path' => 'app/Http/Controllers/UserController.php', 'raw_patch' => "@@ -1 +1 @@\n+return 1;\n"]);

    (new AnalyzeIncursionJob(aiJobPayload(), 'd1'))->handle();

    $sensitive = PullRequestFile::where('pull_request_id', $pr->id)->where('file_path', '.env.production')->first();
    $plain = PullRequestFile::where('pull_request_id', $pr->id)->where('file_path', 'app/Http/Controllers/UserController.php')->first();

    expect($sensitive->is_sensitive)->toBeTrue()
        ->and($plain->is_sensitive)->toBeFalse();
});

it('persists a degraded assessment when no api key is configured', function (): void {
    Http::preventStrayRequests();
    config()->set('services.openrouter.api_key', null);

    $pr = aiJobSeedPullRequest(['head_sha' => str_repeat('a', 40)]);
    aiJobSeedFile($pr, ['raw_patch' => AI_JOB_SECRET_PATCH]);

    (new AnalyzeIncursionJob(aiJobPayload(), 'd1'))->handle();

    $assessment = RiskAssessment::first();

    expect($assessment)->not->toBeNull()
        ->and($assessment->verdict)->toBe(Verdict::Hostile)
        ->and($assessment->is_degraded)->toBeTrue()
        ->and($assessment->security_score)->toBe(40)
        ->and(AiDecision::count())->toBe(0);
});

it('calls the ai once per chunk and merges the score into the verdict', function (): void {
    Http::preventStrayRequests();
    config()->set('services.openrouter.api_key', 'sk-test');

    $url = config('services.openrouter.api_url').'/chat/completions';
    Http::fake([$url => Http::response(aiJobValidJson(80), 200)]);

    $pr = aiJobSeedPullRequest(['head_sha' => str_repeat('a', 40)]);
    aiJobSeedFile($pr);

    (new AnalyzeIncursionJob(aiJobPayload(), 'd1'))->handle();

    $assessment = RiskAssessment::first();

    expect($assessment)->not->toBeNull()
        ->and($assessment->verdict)->toBe(Verdict::Hostile)
        ->and($assessment->security_score)->toBe(80)
        ->and($assessment->is_degraded)->toBeFalse()
        ->and(AiDecision::count())->toBeGreaterThanOrEqual(1);

    Http::assertSentCount(1);
});

it('records every ai attempt as an immutable ai_decision row', function (): void {
    Http::preventStrayRequests();
    config()->set('services.openrouter.api_key', 'sk-test');
    config()->set('services.openrouter.retry_base_ms', 1);
    config()->set('services.openrouter.retries', 3);
    config()->set('services.openrouter.fallback_models', []);

    $url = config('services.openrouter.api_url').'/chat/completions';
    $calls = 0;
    Http::fake([$url => function () use (&$calls) {
        $calls++;

        if ($calls === 1) {
            return Http::response('{"error":"overloaded"}', 503);
        }

        return Http::response(aiJobValidJson(80), 200);
    }]);

    $pr = aiJobSeedPullRequest(['head_sha' => str_repeat('a', 40)]);
    aiJobSeedFile($pr);

    (new AnalyzeIncursionJob(aiJobPayload(), 'd1'))->handle();

    $rows = AiDecision::orderBy('attempt')->get();

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->attempt)->toBe(1)
        ->and($rows[0]->validity)->toBe(AiDecisionValidity::Failed)
        ->and($rows[1]->attempt)->toBe(2)
        ->and($rows[1]->validity)->toBe(AiDecisionValidity::Valid);
});

it('updates the contributor fingerprint inside the same transaction', function (): void {
    Http::preventStrayRequests();
    config()->set('services.openrouter.api_key', null);

    $pr = aiJobSeedPullRequest(['head_sha' => str_repeat('a', 40)]);
    aiJobSeedFile($pr, ['raw_patch' => AI_JOB_SECRET_PATCH]);

    (new AnalyzeIncursionJob(aiJobPayload(), 'd1'))->handle();

    $row = ContributorRisk::first();

    expect($row)->not->toBeNull()
        ->and($row->author_username)->toBe('devacme')
        ->and($row->total_prs)->toBe(1)
        ->and($row->is_new_contributor)->toBeTrue();
});
