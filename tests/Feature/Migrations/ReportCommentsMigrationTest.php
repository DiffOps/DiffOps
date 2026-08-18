<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates report_comments with the expected columns', function () {
    expect(Schema::hasTable('report_comments'))->toBeTrue();

    $columns = array_column(Schema::getColumns('report_comments'), 'name');

    expect($columns)->toContain('id', 'risk_assessment_id', 'github_comment_id', 'created_at');
});

it('is append-only: created_at exists and updated_at does not', function () {
    expect(Schema::hasColumn('report_comments', 'created_at'))->toBeTrue()
        ->and(Schema::hasColumn('report_comments', 'updated_at'))->toBeFalse();
});

it('rejects a second comment for the same risk assessment', function () {
    $assessmentId = seedAssessmentForComment();

    foreach ([1, 2] as $i) {
        DB::table('report_comments')->insert([
            'id' => (string) Str::uuid(),
            'risk_assessment_id' => $assessmentId,
            'github_comment_id' => 9000 + $i,
            'created_at' => now(),
        ]);
    }
})->throws(QueryException::class);

it('rejects a comment for a non-existent risk assessment', function () {
    DB::table('report_comments')->insert([
        'id' => (string) Str::uuid(),
        'risk_assessment_id' => (string) Str::uuid(),
        'github_comment_id' => 9003,
        'created_at' => now(),
    ]);
})->throws(QueryException::class);

it('cascades comments when the risk assessment is deleted', function () {
    $assessmentId = seedAssessmentForComment();

    DB::table('report_comments')->insert([
        'id' => (string) Str::uuid(),
        'risk_assessment_id' => $assessmentId,
        'github_comment_id' => 9004,
        'created_at' => now(),
    ]);

    DB::table('risk_assessments')->where('id', $assessmentId)->delete();

    expect(DB::table('report_comments')->count())->toBe(0);
});

it('keeps github_comment_id null by default and round-trips an integer', function () {
    $assessmentId = seedAssessmentForComment();

    DB::table('report_comments')->insert([
        'id' => (string) Str::uuid(),
        'risk_assessment_id' => $assessmentId,
        'created_at' => now(),
    ]);

    $before = DB::table('report_comments')->where('risk_assessment_id', $assessmentId)->first();

    expect($before->github_comment_id)->toBeNull();

    DB::table('report_comments')->where('risk_assessment_id', $assessmentId)->update([
        'github_comment_id' => 9005,
    ]);

    $after = DB::table('report_comments')->where('risk_assessment_id', $assessmentId)->first();

    expect((int) $after->github_comment_id)->toBe(9005);
});

function seedAssessmentForComment(): string
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
        'github_pr_number' => 21,
        'title' => 'Add report comments',
        'author_username' => 'dev',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $assessmentId = (string) Str::uuid();

    DB::table('risk_assessments')->insert([
        'id' => $assessmentId,
        'pull_request_id' => $prId,
        'head_sha' => str_repeat('c', 64),
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return $assessmentId;
}
