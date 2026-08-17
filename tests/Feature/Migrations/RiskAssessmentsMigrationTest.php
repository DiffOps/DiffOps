<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates risk_assessments with the expected columns', function () {
    expect(Schema::hasTable('risk_assessments'))->toBeTrue();

    $columns = array_column(Schema::getColumns('risk_assessments'), 'name');

    expect($columns)->toContain(
        'id', 'pull_request_id', 'head_sha', 'verdict', 'defcon_level', 'security_score',
        'risk_level', 'summary', 'compliance_checks', 'execution_time_ms', 'is_degraded',
        'created_at', 'updated_at'
    );
});

it('defaults verdict, defcon, score, risk_level and is_degraded', function () {
    $prId = seedPullRequestForRisk();

    DB::table('risk_assessments')->insert([
        'id' => (string) Str::uuid(),
        'pull_request_id' => $prId,
        'head_sha' => str_repeat('a', 64),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $assessment = DB::table('risk_assessments')->where('pull_request_id', $prId)->first();

    expect($assessment->verdict)->toBe('clear')
        ->and((int) $assessment->defcon_level)->toBe(5)
        ->and((int) $assessment->security_score)->toBe(0)
        ->and($assessment->risk_level)->toBe('low')
        ->and((bool) $assessment->is_degraded)->toBeFalse();
});

it('rejects duplicate (pull_request_id, head_sha) pairs', function () {
    $prId = seedPullRequestForRisk();

    foreach ([1, 2] as $i) {
        DB::table('risk_assessments')->insert([
            'id' => (string) Str::uuid(),
            'pull_request_id' => $prId,
            'head_sha' => str_repeat('a', 64),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
})->throws(QueryException::class);

it('rejects a security_score above 100 via check constraint', function () {
    $prId = seedPullRequestForRisk();

    DB::table('risk_assessments')->insert([
        'id' => (string) Str::uuid(),
        'pull_request_id' => $prId,
        'head_sha' => str_repeat('a', 64),
        'security_score' => 150,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
})->throws(QueryException::class);

it('rejects a defcon_level above 5 via check constraint', function () {
    $prId = seedPullRequestForRisk();

    DB::table('risk_assessments')->insert([
        'id' => (string) Str::uuid(),
        'pull_request_id' => $prId,
        'head_sha' => str_repeat('a', 64),
        'defcon_level' => 9,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
})->throws(QueryException::class);

it('round-trips the compliance_checks json column', function () {
    $prId = seedPullRequestForRisk();

    DB::table('risk_assessments')->insert([
        'id' => (string) Str::uuid(),
        'pull_request_id' => $prId,
        'head_sha' => str_repeat('a', 64),
        'compliance_checks' => json_encode(['soc2' => true, 'pci' => false]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $assessment = DB::table('risk_assessments')->where('pull_request_id', $prId)->first();

    expect(json_decode($assessment->compliance_checks, true))->toBe(['soc2' => true, 'pci' => false]);
});

it('cascades assessments when the pull request is deleted', function () {
    $prId = seedPullRequestForRisk();

    DB::table('risk_assessments')->insert([
        'id' => (string) Str::uuid(),
        'pull_request_id' => $prId,
        'head_sha' => str_repeat('a', 64),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('pull_requests')->where('id', $prId)->delete();

    expect(DB::table('risk_assessments')->count())->toBe(0);
});

function seedPullRequestForRisk(): string
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
        'github_pr_number' => 9,
        'title' => 'Add risk scanning',
        'author_username' => 'dev',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return $prId;
}
