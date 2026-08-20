<?php

declare(strict_types=1);

use App\Models\ContributorRisk;
use App\Models\Organization;
use App\Models\PullRequest;
use App\Models\RiskAssessment;
use App\Services\Risk\RiskScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function riskSeed(string $author = 'devacme', string $verdict = 'clear', int $findings = 0): array
{
    $org = Organization::create(['name' => 'Acme', 'slug' => 'acme-'.Str::uuid()]);

    $pr = PullRequest::create([
        'organization_id' => $org->id,
        'github_repo_id' => 424242,
        'repo_full_name' => 'acme/web',
        'github_pr_number' => 42,
        'title' => 'Add rate limiting',
        'author_username' => $author,
        'author_avatar_url' => null,
        'base_ref' => 'main',
        'head_ref' => 'feat/x',
        'head_sha' => str_repeat('a', 40),
        'state' => 'open',
        'is_draft' => false,
    ]);

    $assessment = RiskAssessment::create([
        'pull_request_id' => $pr->id,
        'head_sha' => str_repeat('a', 40),
        'verdict' => $verdict,
        'defcon_level' => 5,
        'security_score' => 0,
        'risk_level' => 'low',
        'compliance_checks' => array_fill(0, $findings, [
            'category' => 'x',
            'severity' => 'low',
            'file_path' => 'a.php',
            'description' => 'x',
        ]),
        'is_degraded' => true,
    ]);

    return [$org, $pr, $assessment];
}

function applyFingerprint(Organization $org, PullRequest $pr, RiskAssessment $assessment): void
{
    app(RiskScoringService::class)->updateFingerprint($org, $pr, $assessment);
}

it('creates the fingerprint on the first analysis', function (): void {
    [$org, $pr, $assessment] = riskSeed();

    applyFingerprint($org, $pr, $assessment);

    $row = ContributorRisk::first();

    expect($row)->not->toBeNull()
        ->and($row->organization_id)->toBe($org->id)
        ->and($row->author_username)->toBe('devacme')
        ->and($row->total_prs)->toBe(1)
        ->and($row->is_new_contributor)->toBeTrue()
        ->and(ContributorRisk::count())->toBe(1);
});

it('upserts the fingerprint without duplicating rows', function (): void {
    [$org, $pr, $assessment] = riskSeed();

    applyFingerprint($org, $pr, $assessment);
    applyFingerprint($org, $pr, $assessment);

    expect(ContributorRisk::count())->toBe(1)
        ->and(ContributorRisk::first()->total_prs)->toBe(2);
});

it('increments the flagged counter for a flagged verdict', function (): void {
    [$org, $pr, $assessment] = riskSeed(verdict: 'flagged', findings: 1);

    applyFingerprint($org, $pr, $assessment);

    $row = ContributorRisk::first();

    expect($row->flagged_prs)->toBe(1)
        ->and($row->hostile_prs)->toBe(0);
});

it('increments the hostile counter for a hostile verdict', function (): void {
    [$org, $pr, $assessment] = riskSeed(verdict: 'hostile', findings: 3);

    applyFingerprint($org, $pr, $assessment);

    $row = ContributorRisk::first();

    expect($row->hostile_prs)->toBe(1)
        ->and($row->flagged_prs)->toBe(0);
});

it('counts only the total for a clear verdict', function (): void {
    [$org, $pr, $assessment] = riskSeed(verdict: 'clear');

    applyFingerprint($org, $pr, $assessment);

    $row = ContributorRisk::first();

    expect($row->total_prs)->toBe(1)
        ->and($row->flagged_prs)->toBe(0)
        ->and($row->hostile_prs)->toBe(0);
});

it('averages the findings density across analyses', function (): void {
    [$org, $pr, $firstAssessment] = riskSeed(verdict: 'flagged', findings: 2);
    applyFingerprint($org, $pr, $firstAssessment);

    $secondPr = PullRequest::create([
        'organization_id' => $org->id,
        'github_repo_id' => 424242,
        'repo_full_name' => 'acme/web',
        'github_pr_number' => 43,
        'title' => 'Second change',
        'author_username' => 'devacme',
        'author_avatar_url' => null,
        'base_ref' => 'main',
        'head_ref' => 'feat/y',
        'head_sha' => str_repeat('b', 40),
        'state' => 'open',
        'is_draft' => false,
    ]);

    $secondAssessment = RiskAssessment::create([
        'pull_request_id' => $secondPr->id,
        'head_sha' => str_repeat('b', 40),
        'verdict' => 'clear',
        'defcon_level' => 5,
        'security_score' => 0,
        'risk_level' => 'low',
        'compliance_checks' => [],
        'is_degraded' => true,
    ]);

    applyFingerprint($org, $secondPr, $secondAssessment);

    expect((float) ContributorRisk::first()->avg_findings_per_pr)->toBe(1.0);
});

it('flips is_new_contributor after the second pull request', function (): void {
    [$org, $pr, $first] = riskSeed();

    $secondPr = PullRequest::create([
        'organization_id' => $org->id,
        'github_repo_id' => 424242,
        'repo_full_name' => 'acme/web',
        'github_pr_number' => 45,
        'title' => 'Second PR',
        'author_username' => 'devacme',
        'author_avatar_url' => null,
        'base_ref' => 'main',
        'head_ref' => 'feat/w',
        'head_sha' => str_repeat('d', 40),
        'state' => 'open',
        'is_draft' => false,
    ]);

    $second = RiskAssessment::create([
        'pull_request_id' => $secondPr->id,
        'head_sha' => str_repeat('d', 40),
        'verdict' => 'clear',
        'defcon_level' => 5,
        'security_score' => 0,
        'risk_level' => 'low',
        'compliance_checks' => [],
        'is_degraded' => true,
    ]);

    applyFingerprint($org, $pr, $first);
    applyFingerprint($org, $secondPr, $second);

    expect(ContributorRisk::first()->is_new_contributor)->toBeFalse();
});

it('computes a deterministic score inside the zero to hundred band', function (): void {
    [$org, $pr, $hostile] = riskSeed(verdict: 'hostile', findings: 3);

    applyFingerprint($org, $pr, $hostile);
    $first = ContributorRisk::first();

    $secondPr = PullRequest::create([
        'organization_id' => $org->id,
        'github_repo_id' => 424242,
        'repo_full_name' => 'acme/web',
        'github_pr_number' => 44,
        'title' => 'Third change',
        'author_username' => 'devacme',
        'author_avatar_url' => null,
        'base_ref' => 'main',
        'head_ref' => 'feat/z',
        'head_sha' => str_repeat('c', 40),
        'state' => 'open',
        'is_draft' => false,
    ]);

    $secondAssessment = RiskAssessment::create([
        'pull_request_id' => $secondPr->id,
        'head_sha' => str_repeat('c', 40),
        'verdict' => 'clear',
        'defcon_level' => 5,
        'security_score' => 0,
        'risk_level' => 'low',
        'compliance_checks' => [],
        'is_degraded' => true,
    ]);

    applyFingerprint($org, $secondPr, $secondAssessment);
    $second = ContributorRisk::first();

    expect($first->score)->toBe(63)
        ->and($second->score)->toBe(32)
        ->and($second->score)->toBeGreaterThanOrEqual(0)
        ->and($second->score)->toBeLessThanOrEqual(100);
});
