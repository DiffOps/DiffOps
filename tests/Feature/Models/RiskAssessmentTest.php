<?php

use App\Enums\DefconLevel;
use App\Enums\RiskLevel;
use App\Enums\Verdict;
use App\Models\AiDecision;
use App\Models\Organization;
use App\Models\PullRequest;
use App\Models\RiskAssessment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('generates a uuid primary key on creation', function () {
    $assessment = createAssessment();

    expect($assessment->id)->toBeString()
        ->and(strlen($assessment->id))->toBe(36);
});

it('accepts the assessment payload through mass assignment', function () {
    $assessment = createAssessment([
        'head_sha' => str_repeat('b', 64),
        'verdict' => 'flagged',
        'defcon_level' => 2,
        'security_score' => 71,
        'risk_level' => 'medium',
        'summary' => 'Credential rotation required.',
        'compliance_checks' => ['secrets' => true, 'deps' => false],
        'execution_time_ms' => 840,
        'is_degraded' => true,
    ]);

    expect($assessment->head_sha)->toBe(str_repeat('b', 64))
        ->and($assessment->verdict)->toBe(Verdict::Flagged)
        ->and($assessment->defcon_level)->toBe(DefconLevel::Two)
        ->and($assessment->security_score)->toBe(71)
        ->and($assessment->risk_level)->toBe(RiskLevel::Medium)
        ->and($assessment->summary)->toBe('Credential rotation required.')
        ->and($assessment->compliance_checks)->toBe(['secrets' => true, 'deps' => false])
        ->and($assessment->execution_time_ms)->toBe(840)
        ->and($assessment->is_degraded)->toBeTrue();
});

it('casts verdict, defcon_level and risk_level with raw round-trip', function () {
    $assessment = createAssessment([
        'verdict' => 'hostile',
        'defcon_level' => 1,
        'risk_level' => 'high',
    ]);

    expect($assessment->verdict)->toBe(Verdict::Hostile)
        ->and($assessment->defcon_level)->toBe(DefconLevel::One)
        ->and($assessment->risk_level)->toBe(RiskLevel::High)
        ->and($assessment->getRawOriginal('verdict'))->toBe('hostile')
        ->and($assessment->getRawOriginal('defcon_level'))->toBe(1)
        ->and($assessment->getRawOriginal('risk_level'))->toBe('high');
});

it('belongs to a pull request and relates to ai decisions', function () {
    $assessment = createAssessment();

    AiDecision::create([
        'risk_assessment_id' => $assessment->id,
        'model_used' => 'deepseek/deepseek-chat',
        'attempt' => 1,
        'validity' => 'valid',
    ]);

    expect($assessment->pullRequest)->toBeInstanceOf(PullRequest::class)
        ->and($assessment->aiDecisions)->toHaveCount(1)
        ->and($assessment->aiDecisions->first())->toBeInstanceOf(AiDecision::class);
});

function createAssessment(array $overrides = []): RiskAssessment
{
    $organization = Organization::create(['name' => 'Acme', 'slug' => 'acme']);

    $pr = PullRequest::create([
        'organization_id' => $organization->id,
        'github_repo_id' => 1,
        'repo_full_name' => 'acme/web',
        'github_pr_number' => 10,
        'title' => 'Fix login',
        'author_username' => 'devacme',
    ]);

    return RiskAssessment::create(array_merge([
        'pull_request_id' => $pr->id,
        'head_sha' => str_repeat('a', 64),
        'verdict' => 'clear',
        'defcon_level' => 5,
        'security_score' => 100,
        'risk_level' => 'low',
    ], $overrides));
}
