<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates repo_watchlist with user_id, repository_id and created_at only', function () {
    expect(Schema::hasTable('repo_watchlist'))->toBeTrue();

    $columns = array_column(Schema::getColumns('repo_watchlist'), 'name');

    expect($columns)->toContain('user_id', 'repository_id', 'created_at')
        ->and($columns)->not->toContain('id')
        ->and($columns)->not->toContain('updated_at');
});

it('rejects a duplicate (user_id, repository_id) pair via composite primary key', function () {
    [$userId, $repositoryId] = seedRepoAndUserForWatchlist();

    foreach ([1, 2] as $i) {
        DB::table('repo_watchlist')->insert([
            'user_id' => $userId,
            'repository_id' => $repositoryId,
            'created_at' => now(),
        ]);
    }
})->throws(QueryException::class);

it('rejects a watchlist row for a non-existent user', function () {
    [, $repositoryId] = seedRepoAndUserForWatchlist();

    DB::table('repo_watchlist')->insert([
        'user_id' => 999999,
        'repository_id' => $repositoryId,
        'created_at' => now(),
    ]);
})->throws(QueryException::class);

it('rejects a watchlist row for a non-existent repository', function () {
    [$userId] = seedRepoAndUserForWatchlist();

    DB::table('repo_watchlist')->insert([
        'user_id' => $userId,
        'repository_id' => (string) Str::uuid(),
        'created_at' => now(),
    ]);
})->throws(QueryException::class);

it('cascades watchlist rows when the user is deleted', function () {
    [$userId, $repositoryId] = seedRepoAndUserForWatchlist();

    DB::table('users')->where('id', $userId)->delete();

    expect(DB::table('repo_watchlist')->count())->toBe(0);
});

it('cascades watchlist rows when the repository is deleted', function () {
    [$userId, $repositoryId] = seedRepoAndUserForWatchlist();

    DB::table('repositories')->where('id', $repositoryId)->delete();

    expect(DB::table('repo_watchlist')->count())->toBe(0);
});

function seedRepoAndUserForWatchlist(): array
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

    $repositoryId = (string) Str::uuid();

    DB::table('repositories')->insert([
        'id' => $repositoryId,
        'organization_id' => $orgId,
        'github_repo_id' => 7001,
        'full_name' => 'acme/watchlist',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $userId = DB::table('users')->insertGetId([
        'name' => 'Watchlist Operator',
        'email' => 'watchlist@example.com',
        'password' => bcrypt('password'),
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return [$userId, $repositoryId];
}
