<?php

use App\Models\Organization;
use App\Models\PullRequest;
use App\Models\ReportComment;
use App\Models\RiskAssessment;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('generates a uuid primary key on creation', function () {
    $comment = createReportComment();

    expect($comment->id)->toBeString()
        ->and(strlen($comment->id))->toBe(36)
        ->and(ReportComment::query()->whereKey($comment->id)->exists())->toBeTrue();
});

it('accepts the github comment id through mass assignment', function () {
    $comment = createReportComment(['github_comment_id' => 987654]);

    expect($comment->github_comment_id)->toBe(987654)
        ->and($comment->getRawOriginal('github_comment_id'))->toBe(987654);
});

it('allows a null github comment id before the comment is posted', function () {
    $comment = createReportComment(['github_comment_id' => null]);

    expect($comment->github_comment_id)->toBeNull();
});

it('belongs to a risk assessment', function () {
    $comment = createReportComment();

    expect($comment->riskAssessment)->toBeInstanceOf(RiskAssessment::class);
});

it('is append-only: updated_at is disabled', function () {
    $comment = createReportComment();

    expect($comment->getUpdatedAtColumn())->toBeNull()
        ->and(Schema::hasColumn('report_comments', 'updated_at'))->toBeFalse();
});

it('enforces one report comment per risk assessment', function () {
    createReportComment(['github_comment_id' => 100]);

    createReportComment(['github_comment_id' => 200]);
})->throws(QueryException::class);

function createReportComment(array $overrides = []): ReportComment
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

    return ReportComment::create(array_merge([
        'risk_assessment_id' => $assessment->id,
        'github_comment_id' => 123456,
    ], $overrides));
}
