<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates repositories with the expected columns', function () {
    expect(Schema::hasTable('repositories'))->toBeTrue();

    $columns = array_column(Schema::getColumns('repositories'), 'name');

    expect($columns)->toContain(
        'id', 'organization_id', 'github_repo_id', 'full_name', 'is_private',
        'comment_on_pr', 'escalate_on_hostile', 'escalation_webhook_url',
        'created_at', 'updated_at'
    );
});

it('defaults privacy and tactical flags to false', function () {
    $orgId = seedOrganizationForRepo();

    DB::table('repositories')->insert([
        'id' => (string) Str::uuid(),
        'organization_id' => $orgId,
        'github_repo_id' => 1001,
        'full_name' => 'acme/core',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $repo = DB::table('repositories')->where('full_name', 'acme/core')->first();

    expect((bool) $repo->is_private)->toBeFalse()
        ->and((bool) $repo->comment_on_pr)->toBeFalse()
        ->and((bool) $repo->escalate_on_hostile)->toBeFalse();
});

it('rejects a repository for a non-existent organization', function () {
    DB::table('repositories')->insert([
        'id' => (string) Str::uuid(),
        'organization_id' => (string) Str::uuid(),
        'github_repo_id' => 1002,
        'full_name' => 'acme/missing',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
})->throws(QueryException::class);

it('rejects duplicate (organization_id, github_repo_id) pairs', function () {
    $orgId = seedOrganizationForRepo();

    foreach ([1, 2] as $i) {
        DB::table('repositories')->insert([
            'id' => (string) Str::uuid(),
            'organization_id' => $orgId,
            'github_repo_id' => 2001,
            'full_name' => "acme/repo-{$i}",
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
})->throws(QueryException::class);

it('rejects duplicate (organization_id, full_name) pairs even with a different github_repo_id', function () {
    $orgId = seedOrganizationForRepo();

    foreach ([3001, 3002] as $repoId) {
        DB::table('repositories')->insert([
            'id' => (string) Str::uuid(),
            'organization_id' => $orgId,
            'github_repo_id' => $repoId,
            'full_name' => 'acme/duplicated',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
})->throws(QueryException::class);

it('cascades repositories when the organization is deleted', function () {
    $orgId = seedOrganizationForRepo();

    DB::table('repositories')->insert([
        'id' => (string) Str::uuid(),
        'organization_id' => $orgId,
        'github_repo_id' => 4001,
        'full_name' => 'acme/cascade',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('organizations')->where('id', $orgId)->delete();

    expect(DB::table('repositories')->count())->toBe(0);
});

it('keeps escalation_webhook_url null by default and round-trips a value', function () {
    $orgId = seedOrganizationForRepo();

    DB::table('repositories')->insert([
        'id' => (string) Str::uuid(),
        'organization_id' => $orgId,
        'github_repo_id' => 5001,
        'full_name' => 'acme/webhook',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $before = DB::table('repositories')->where('full_name', 'acme/webhook')->first();

    expect($before->escalation_webhook_url)->toBeNull();

    DB::table('repositories')->where('full_name', 'acme/webhook')->update([
        'escalation_webhook_url' => 'https://hooks.example.com/incursion',
    ]);

    $after = DB::table('repositories')->where('full_name', 'acme/webhook')->first();

    expect($after->escalation_webhook_url)->toBe('https://hooks.example.com/incursion');
});

function seedOrganizationForRepo(): string
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

    return $orgId;
}
