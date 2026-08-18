<?php

use App\Models\Organization;
use App\Models\Repository;
use App\Models\RepoWatchlist;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates a composite key entry without a surrogate id', function () {
    [$user, $repository] = createWatchlistEntry();

    $entry = RepoWatchlist::create([
        'user_id' => $user->id,
        'repository_id' => $repository->id,
    ]);

    expect($entry->exists)->toBeTrue()
        ->and($entry->wasRecentlyCreated)->toBeTrue()
        ->and($entry->user_id)->toBeInt()
        ->and($entry->repository_id)->toBeString()
        ->and(strlen($entry->repository_id))->toBe(36)
        ->and($entry->getKey())->toBeNull()
        ->and(Schema::hasColumn('repo_watchlist', 'id'))->toBeFalse()
        ->and(Schema::hasColumn('repo_watchlist', 'updated_at'))->toBeFalse();
});

it('persists the composite key through mass assignment', function () {
    [$user, $repository] = createWatchlistEntry();

    RepoWatchlist::create([
        'user_id' => $user->id,
        'repository_id' => $repository->id,
    ]);

    $row = DB::table('repo_watchlist')
        ->where('user_id', $user->id)
        ->where('repository_id', $repository->id)
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->created_at)->not->toBeNull();
});

it('rejects a duplicate composite key', function () {
    [$user, $repository] = createWatchlistEntry();

    RepoWatchlist::create([
        'user_id' => $user->id,
        'repository_id' => $repository->id,
    ]);

    RepoWatchlist::create([
        'user_id' => $user->id,
        'repository_id' => $repository->id,
    ]);
})->throws(QueryException::class);

it('belongs to a user and a repository', function () {
    [$user, $repository] = createWatchlistEntry();

    $entry = RepoWatchlist::create([
        'user_id' => $user->id,
        'repository_id' => $repository->id,
    ]);

    expect($entry->user)->toBeInstanceOf(User::class)
        ->and($entry->repository)->toBeInstanceOf(Repository::class);
});

it('is append-only: updated_at is disabled', function () {
    [$user, $repository] = createWatchlistEntry();

    $entry = RepoWatchlist::create([
        'user_id' => $user->id,
        'repository_id' => $repository->id,
    ]);

    expect($entry->getUpdatedAtColumn())->toBeNull()
        ->and(Schema::hasColumn('repo_watchlist', 'updated_at'))->toBeFalse();
});

/**
 * @return array{0: User, 1: Repository}
 */
function createWatchlistEntry(): array
{
    $user = User::create([
        'name' => 'Dev',
        'email' => 'dev@acme.test',
        'password' => bcrypt('secret'),
    ]);

    $organization = Organization::create(['name' => 'Acme', 'slug' => 'acme']);

    $repository = Repository::create([
        'organization_id' => $organization->id,
        'github_repo_id' => 1,
        'full_name' => 'acme/web',
    ]);

    return [$user, $repository];
}
