<?php

declare(strict_types=1);

use App\Jobs\PostReconCommentJob;
use App\Models\Organization;
use App\Models\ReportComment;
use App\Services\GitHub\GitHubAppTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\ReconCommentFixtures;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // F1 prerequisite (documented in code): a GitHub App installation token is
    // required to post. Tests stub the token service so no real exchange/network
    // happens — the GitHub App only needs "Pull requests: Write" to post for real.
    $tokens = Mockery::mock(GitHubAppTokenService::class);
    $tokens->shouldReceive('tokenForInstallation')->andReturn('fake-install-token');

    app()->instance(GitHubAppTokenService::class, $tokens);
});

function jobOrg(string $name = 'Recon'): Organization
{
    return Organization::create(['name' => $name, 'slug' => $name.'-'.\Illuminate\Support\Str::uuid()]);
}

function commentsBody(): string
{
    $posts = collect(Http::recorded())
        ->filter(fn (array $pair): bool => str_contains($pair[0]->url(), '/issues/') && $pair[0]->method() === 'POST');

    if ($posts->isEmpty()) {
        return '';
    }

    // The request body is JSON {"body": "<markdown>"}; decode to inspect markdown.
    $payload = json_decode($posts->first()[0]->body(), true);

    return (string) ($payload['body'] ?? '');
}

it('posts exactly one github comment per assessment', function (): void {
    $org = jobOrg();
    [$repo, $pr, $assessment] = ReconCommentFixtures::scenario(
        $org,
        ['comment_on_pr' => true, 'installation_id' => 99887766]
    );

    Http::fake([
        '*/repos/*/*/issues/*/comments' => Http::response(['id' => 123456], 201),
    ]);

    PostReconCommentJob::dispatch($assessment);

    Http::assertSent(fn (Request $request): bool =>
        $request->url() === 'https://api.github.com/repos/alpha/web/issues/77/comments'
        && $request->method() === 'POST'
        && $request->hasHeader('Authorization', 'Bearer fake-install-token'));

    expect(ReportComment::count())->toBe(1);
    $comment = ReportComment::first();
    expect($comment->risk_assessment_id)->toBe($assessment->id)
        ->and($comment->github_comment_id)->toBe(123456);

    $body = commentsBody();
    expect($body)->toContain('Recon Report')
        ->and($body)->toContain('HOSTILE')
        ->and($body)->toContain('DEFCON 1')
        ->and($body)->toContain('pull/77')
        ->and($body)->toContain('/incursions/'.$assessment->id);
});

it('does not duplicate the comment on re-post', function (): void {
    $org = jobOrg();
    [$repo, $pr, $assessment] = ReconCommentFixtures::scenario(
        $org,
        ['comment_on_pr' => true, 'installation_id' => 99887766]
    );

    Http::fake([
        '*/repos/*/*/issues/*/comments' => Http::response(['id' => 123456], 201),
    ]);

    PostReconCommentJob::dispatch($assessment);
    PostReconCommentJob::dispatch($assessment);

    $posts = collect(Http::recorded())
        ->filter(fn (array $pair): bool => str_contains($pair[0]->url(), '/issues/') && $pair[0]->method() === 'POST');

    expect($posts)->toHaveCount(1)
        ->and(ReportComment::count())->toBe(1);
});

it('respects the repository toggle off', function (): void {
    $org = jobOrg();
    [$repo, $pr, $assessment] = ReconCommentFixtures::scenario(
        $org,
        ['comment_on_pr' => false, 'installation_id' => 99887766]
    );

    Http::fake([
        '*/repos/*/*/issues/*/comments' => Http::response(['id' => 123456], 201),
    ]);

    PostReconCommentJob::dispatch($assessment);

    Http::assertNothingSent();
    expect(ReportComment::count())->toBe(0);
});

it('skips when installation_id is missing', function (): void {
    $org = jobOrg();
    [$repo, $pr, $assessment] = ReconCommentFixtures::scenario(
        $org,
        ['comment_on_pr' => true, 'installation_id' => null]
    );

    Http::fake([
        '*/repos/*/*/issues/*/comments' => Http::response(['id' => 123456], 201),
    ]);

    PostReconCommentJob::dispatch($assessment);

    expect(ReportComment::count())->toBe(0);
    Http::assertNothingSent();
});

it('builds markdown from compliance_checks findings', function (): void {
    $org = jobOrg();
    [$repo, $pr, $assessment] = ReconCommentFixtures::scenario(
        $org,
        ['comment_on_pr' => true, 'installation_id' => 99887766],
        [],
        [
            'verdict' => 'flagged',
            'defcon_level' => 3,
            'security_score' => 55,
            'compliance_checks' => [
                [
                    'category' => 'secret-leak',
                    'severity' => 'critical',
                    'file_path' => 'config/secrets.php',
                    'description' => 'Hardcoded API key',
                ],
            ],
        ]
    );

    Http::fake([
        '*/repos/*/*/issues/*/comments' => Http::response(['id' => 654321], 201),
    ]);

    PostReconCommentJob::dispatch($assessment);

    $body = commentsBody();
    expect($body)->toContain('secret-leak')
        ->and($body)->toContain('config/secrets.php')
        ->and($body)->toContain('FLAGGED')
        ->and($body)->toContain('DEFCON 3');
});
