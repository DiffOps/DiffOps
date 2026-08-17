<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates ai_decisions with the expected columns', function () {
    expect(Schema::hasTable('ai_decisions'))->toBeTrue();

    $columns = array_column(Schema::getColumns('ai_decisions'), 'name');

    expect($columns)->toContain(
        'id', 'risk_assessment_id', 'model_used', 'attempt', 'validity', 'raw_response',
        'ai_signals', 'prompt_tokens', 'completion_tokens', 'total_tokens', 'latency_ms', 'created_at'
    );
});

it('defaults attempt to 1 and validity to valid', function () {
    $assessmentId = seedRiskAssessment();

    DB::table('ai_decisions')->insert([
        'id' => (string) Str::uuid(),
        'risk_assessment_id' => $assessmentId,
        'model_used' => 'deepseek/deepseek-chat',
        'created_at' => now(),
    ]);

    $decision = DB::table('ai_decisions')->where('risk_assessment_id', $assessmentId)->first();

    expect((int) $decision->attempt)->toBe(1)
        ->and($decision->validity)->toBe('valid');
});

it('is append-only: created_at exists and updated_at does not', function () {
    expect(Schema::hasColumn('ai_decisions', 'created_at'))->toBeTrue()
        ->and(Schema::hasColumn('ai_decisions', 'updated_at'))->toBeFalse();
});

it('round-trips the ai_signals json column', function () {
    $assessmentId = seedRiskAssessment();

    DB::table('ai_decisions')->insert([
        'id' => (string) Str::uuid(),
        'risk_assessment_id' => $assessmentId,
        'model_used' => 'deepseek/deepseek-chat',
        'ai_signals' => json_encode(['confidence' => 0.87, 'flags' => ['secret']]),
        'created_at' => now(),
    ]);

    $decision = DB::table('ai_decisions')->where('risk_assessment_id', $assessmentId)->first();

    expect(json_decode($decision->ai_signals, true))->toBe(['confidence' => 0.87, 'flags' => ['secret']]);
});

it('cascades decisions when the risk assessment is deleted', function () {
    $assessmentId = seedRiskAssessment();

    DB::table('ai_decisions')->insert([
        'id' => (string) Str::uuid(),
        'risk_assessment_id' => $assessmentId,
        'model_used' => 'deepseek/deepseek-chat',
        'created_at' => now(),
    ]);

    DB::table('risk_assessments')->where('id', $assessmentId)->delete();

    expect(DB::table('ai_decisions')->count())->toBe(0);
});

it('rejects a decision for a non-existent risk assessment', function () {
    DB::table('ai_decisions')->insert([
        'id' => (string) Str::uuid(),
        'risk_assessment_id' => (string) Str::uuid(),
        'model_used' => 'deepseek/deepseek-chat',
        'created_at' => now(),
    ]);
})->throws(QueryException::class);

function seedRiskAssessment(): string
{
    $orgId = (string) Str::uuid();
    $now = now();

    DB::table('organizations')->insert([
        'id' => $orgId,
        'name' => 'Acme',
        'slug' => 'acme',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $prId = (string) Str::uuid();

    DB::table('pull_requests')->insert([
        'id' => $prId,
        'organization_id' => $orgId,
        'github_repo_id' => 1,
        'repo_full_name' => 'acme/web',
        'github_pr_number' => 11,
        'title' => 'Add ai decisions',
        'author_username' => 'dev',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $assessmentId = (string) Str::uuid();

    DB::table('risk_assessments')->insert([
        'id' => $assessmentId,
        'pull_request_id' => $prId,
        'head_sha' => str_repeat('b', 64),
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return $assessmentId;
}
