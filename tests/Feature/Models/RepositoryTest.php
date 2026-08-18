<?php

use App\Models\Organization;
use App\Models\Repository;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('generates a uuid primary key on creation', function () {
    $repository = createRepositoryRecord();

    expect($repository->id)->toBeString()
        ->and(strlen($repository->id))->toBe(36)
        ->and(Repository::query()->whereKey($repository->id)->exists())->toBeTrue();
});

it('accepts the tactical repository config through mass assignment', function () {
    $repository = createRepositoryRecord([
        'github_repo_id' => 4242,
        'full_name' => 'acme/backend',
        'is_private' => true,
        'comment_on_pr' => true,
        'escalate_on_hostile' => true,
        'escalation_webhook_url' => 'https://hooks.example.com/diffops',
    ]);

    expect($repository->github_repo_id)->toBe(4242)
        ->and($repository->full_name)->toBe('acme/backend')
        ->and($repository->is_private)->toBeTrue()
        ->and($repository->comment_on_pr)->toBeTrue()
        ->and($repository->escalate_on_hostile)->toBeTrue()
        ->and($repository->escalation_webhook_url)->toBe('https://hooks.example.com/diffops')
        ->and($repository->wasRecentlyCreated)->toBeTrue();
});

it('casts booleans and github_repo_id with raw round-trip', function () {
    $repository = createRepositoryRecord([
        'github_repo_id' => 4242,
        'is_private' => true,
        'escalate_on_hostile' => false,
    ]);

    expect((int) $repository->is_private)->toBe(1)
        ->and((int) $repository->escalate_on_hostile)->toBe(0)
        ->and((int) $repository->github_repo_id)->toBe(4242)
        ->and($repository->getRawOriginal('github_repo_id'))->toBe(4242);
});

it('belongs to an organization', function () {
    $repository = createRepositoryRecord();

    expect($repository->organization)->toBeInstanceOf(Organization::class);
});

it('enforces the unique organization and github_repo_id pair', function () {
    createRepositoryRecord(['github_repo_id' => 4242]);

    createRepositoryRecord(['github_repo_id' => 4242]);
})->throws(QueryException::class);

it('enforces the unique organization and full_name pair', function () {
    createRepositoryRecord(['full_name' => 'acme/backend']);

    createRepositoryRecord(['full_name' => 'acme/backend']);
})->throws(QueryException::class);

function createRepositoryRecord(array $overrides = []): Repository
{
    $organization = Organization::create(['name' => 'Acme', 'slug' => 'acme']);

    return Repository::create(array_merge([
        'organization_id' => $organization->id,
        'github_repo_id' => 1,
        'full_name' => 'acme/web',
    ], $overrides));
}
