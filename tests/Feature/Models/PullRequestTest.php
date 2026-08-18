<?php

use App\Enums\PrState;
use App\Models\Organization;
use App\Models\PullRequest;
use App\Models\PullRequestFile;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('generates a uuid primary key on creation', function () {
    $pr = createPullRequestRecord();

    expect($pr->id)->toBeString()
        ->and(strlen($pr->id))->toBe(36);
});

it('accepts the full incursion payload through mass assignment', function () {
    $pr = createPullRequestRecord([
        'github_repo_id' => 4242,
        'repo_full_name' => 'acme/backend',
        'github_pr_number' => 77,
        'title' => 'Add rate limiting',
        'author_username' => 'devacme',
        'author_avatar_url' => 'https://avatars.example.com/dev.png',
        'base_ref' => 'main',
        'head_ref' => 'feat/rate-limit',
        'head_sha' => str_repeat('a', 40),
        'state' => 'open',
        'is_draft' => true,
        'closed_at' => null,
    ]);

    expect($pr->github_repo_id)->toBe(4242)
        ->and($pr->repo_full_name)->toBe('acme/backend')
        ->and($pr->github_pr_number)->toBe(77)
        ->and($pr->title)->toBe('Add rate limiting')
        ->and($pr->author_username)->toBe('devacme')
        ->and($pr->author_avatar_url)->toBe('https://avatars.example.com/dev.png')
        ->and($pr->base_ref)->toBe('main')
        ->and($pr->head_ref)->toBe('feat/rate-limit')
        ->and($pr->head_sha)->toBe(str_repeat('a', 40))
        ->and($pr->is_draft)->toBeTrue();
});

it('casts state to PrState and closed_at to Carbon', function () {
    $pr = createPullRequestRecord(['state' => 'merged', 'closed_at' => '2026-08-17 20:00:00']);

    expect($pr->state)->toBeInstanceOf(PrState::class)
        ->and($pr->state)->toBe(PrState::Merged)
        ->and($pr->closed_at)->toBeInstanceOf(Carbon::class)
        ->and($pr->getRawOriginal('state'))->toBe('merged');
});

it('belongs to an organization and relates to files', function () {
    $pr = createPullRequestRecord();

    PullRequestFile::create([
        'pull_request_id' => $pr->id,
        'file_path' => 'app/Services/Triage.php',
        'status' => 'modified',
        'additions' => 10,
        'deletions' => 2,
    ]);

    expect($pr->organization)->toBeInstanceOf(Organization::class)
        ->and($pr->files)->toHaveCount(1)
        ->and($pr->files->first())->toBeInstanceOf(PullRequestFile::class);
});

it('enforces the unique organization, repo and pr number triplet', function () {
    createPullRequestRecord(['github_pr_number' => 10]);

    createPullRequestRecord(['github_pr_number' => 10]);
})->throws(QueryException::class);

function createPullRequestRecord(array $overrides = []): PullRequest
{
    $organization = Organization::create(['name' => 'Acme', 'slug' => 'acme']);

    return PullRequest::create(array_merge([
        'organization_id' => $organization->id,
        'github_repo_id' => 1,
        'repo_full_name' => 'acme/web',
        'github_pr_number' => 10,
        'title' => 'Fix login',
        'author_username' => 'devacme',
    ], $overrides));
}
