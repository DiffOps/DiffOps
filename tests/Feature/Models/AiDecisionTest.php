<?php

use App\Enums\AiDecisionValidity;
use App\Models\AiDecision;
use App\Models\Organization;
use App\Models\PullRequest;
use App\Models\RiskAssessment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('generates a uuid primary key on creation', function () {
    $decision = createDecision();

    expect($decision->id)->toBeString()
        ->and(strlen($decision->id))->toBe(36);
});

it('accepts the decision payload through mass assignment', function () {
    $decision = createDecision([
        'model_used' => 'deepseek/deepseek-chat',
        'attempt' => 2,
        'validity' => 'repaired',
        'raw_response' => '{"verdict":"clear"}',
        'ai_signals' => ['confidence' => 0.93, 'repair' => true],
        'prompt_tokens' => 1200,
        'completion_tokens' => 300,
        'total_tokens' => 1500,
        'latency_ms' => 2400,
    ]);

    expect($decision->model_used)->toBe('deepseek/deepseek-chat')
        ->and($decision->attempt)->toBe(2)
        ->and($decision->validity)->toBe(AiDecisionValidity::Repaired)
        ->and($decision->raw_response)->toBe('{"verdict":"clear"}')
        ->and($decision->ai_signals)->toBe(['confidence' => 0.93, 'repair' => true])
        ->and($decision->prompt_tokens)->toBe(1200)
        ->and($decision->completion_tokens)->toBe(300)
        ->and($decision->total_tokens)->toBe(1500)
        ->and($decision->latency_ms)->toBe(2400);
});

it('casts validity and ai_signals with raw round-trip', function () {
    $decision = createDecision(['validity' => 'failed', 'ai_signals' => ['error' => 'parse']]);

    expect($decision->validity)->toBe(AiDecisionValidity::Failed)
        ->and($decision->ai_signals)->toBe(['error' => 'parse'])
        ->and($decision->getRawOriginal('validity'))->toBe('failed');
});

it('belongs to a risk assessment', function () {
    $decision = createDecision();

    expect($decision->riskAssessment)->toBeInstanceOf(RiskAssessment::class);
});

it('is append-only: updated_at is disabled', function () {
    $decision = createDecision();

    expect($decision->getUpdatedAtColumn())->toBeNull()
        ->and(Schema::hasColumn('ai_decisions', 'updated_at'))->toBeFalse();
});

function createDecision(array $overrides = []): AiDecision
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

    $assessment = RiskAssessment::create([
        'pull_request_id' => $pr->id,
        'head_sha' => str_repeat('a', 64),
    ]);

    return AiDecision::create(array_merge([
        'risk_assessment_id' => $assessment->id,
        'model_used' => 'deepseek/deepseek-chat',
        'attempt' => 1,
        'validity' => 'valid',
    ], $overrides));
}
