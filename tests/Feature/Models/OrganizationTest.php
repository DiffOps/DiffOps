<?php

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PullRequest;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('generates a uuid primary key on creation', function () {
    $organization = Organization::create([
        'name' => 'Acme',
        'slug' => 'acme',
    ]);

    expect($organization->id)->toBeString()
        ->and(strlen($organization->id))->toBe(36)
        ->and(Organization::query()->whereKey($organization->id)->exists())->toBeTrue();
});

it('accepts name and slug through mass assignment', function () {
    $organization = Organization::create([
        'name' => 'Delta Force',
        'slug' => 'delta-force',
    ]);

    expect($organization->name)->toBe('Delta Force')
        ->and($organization->slug)->toBe('delta-force')
        ->and($organization->wasRecentlyCreated)->toBeTrue();
});

it('enforces a unique slug', function () {
    Organization::create(['name' => 'Acme', 'slug' => 'acme']);

    Organization::create(['name' => 'Acme Copy', 'slug' => 'acme']);
})->throws(QueryException::class);

it('relates to members', function () {
    $organization = Organization::create(['name' => 'Acme', 'slug' => 'acme']);

    $user = User::create([
        'name' => 'Dev',
        'email' => 'dev@acme.test',
        'password' => bcrypt('secret'),
    ]);

    OrganizationMember::create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role' => 'operator',
    ]);

    expect($organization->members)->toHaveCount(1)
        ->and($organization->members->first())->toBeInstanceOf(OrganizationMember::class);
});

it('relates to pull requests', function () {
    $organization = Organization::create(['name' => 'Acme', 'slug' => 'acme']);

    PullRequest::create([
        'organization_id' => $organization->id,
        'github_repo_id' => 1,
        'repo_full_name' => 'acme/web',
        'github_pr_number' => 10,
        'title' => 'Fix login',
        'author_username' => 'dev',
    ]);

    expect($organization->pullRequests)->toHaveCount(1)
        ->and($organization->pullRequests->first())->toBeInstanceOf(PullRequest::class);
});
