<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates pull_requests with the expected columns', function () {
    expect(Schema::hasTable('pull_requests'))->toBeTrue();

    $columns = array_column(Schema::getColumns('pull_requests'), 'name');

    expect($columns)->toContain(
        'id', 'organization_id', 'github_repo_id', 'repo_full_name', 'github_pr_number',
        'title', 'author_username', 'author_avatar_url', 'base_ref', 'head_ref', 'head_sha',
        'state', 'is_draft', 'closed_at', 'created_at', 'updated_at'
    );
});

it('defaults state to open and is_draft to false', function () {
    $orgId = seedOrganization();
    $prId = insertPullRequest($orgId, 'acme/web', 42, 'Fix login');

    $pr = DB::table('pull_requests')->where('id', $prId)->first();

    expect($pr->state)->toBe('open')
        ->and((bool) $pr->is_draft)->toBeFalse();
});

it('rejects duplicate (organization_id, repo_full_name, github_pr_number)', function () {
    $orgId = seedOrganization();

    foreach ([1, 2] as $i) {
        insertPullRequest($orgId, 'acme/web', 42, 'Fix login #'.$i);
    }
})->throws(QueryException::class);

it('rejects a pull request for a non-existent organization', function () {
    DB::table('pull_requests')->insert([
        'id' => (string) Str::uuid(),
        'organization_id' => (string) Str::uuid(),
        'github_repo_id' => 1,
        'repo_full_name' => 'acme/web',
        'github_pr_number' => 1,
        'title' => 'Nope',
        'author_username' => 'attacker',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
})->throws(QueryException::class);

it('cascades pull requests when the organization is deleted', function () {
    $orgId = seedOrganization();
    insertPullRequest($orgId, 'acme/web', 42, 'Fix login');

    DB::table('organizations')->where('id', $orgId)->delete();

    expect(DB::table('pull_requests')->count())->toBe(0);
});

it('indexes author_username', function () {
    expect(Schema::hasIndex('pull_requests', 'pull_requests_author_username_index'))->toBeTrue();
});

function seedOrganization(): string
{
    $orgId = (string) Str::uuid();

    DB::table('organizations')->insert([
        'id' => $orgId,
        'name' => 'Acme',
        'slug' => 'acme',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $orgId;
}

function insertPullRequest(string $orgId, string $repo, int $number, string $title): string
{
    $prId = (string) Str::uuid();
    $now = now();

    DB::table('pull_requests')->insert([
        'id' => $prId,
        'organization_id' => $orgId,
        'github_repo_id' => 100 + $number,
        'repo_full_name' => $repo,
        'github_pr_number' => $number,
        'title' => $title,
        'author_username' => 'dev-'.$number,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return $prId;
}
