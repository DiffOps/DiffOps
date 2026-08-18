<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('generates a uuid primary key on creation', function () {
    $member = createMember();

    expect($member->id)->toBeString()
        ->and(strlen($member->id))->toBe(36);
});

it('accepts organization_id, user_id and role through mass assignment', function () {
    $organization = Organization::create(['name' => 'Acme', 'slug' => 'acme']);
    $user = createUser();

    $member = OrganizationMember::create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role' => 'commander',
    ]);

    expect($member->organization_id)->toBe($organization->id)
        ->and($member->user_id)->toBe($user->id)
        ->and($member->role)->toBe(OrganizationRole::Commander);
});

it('casts role to the OrganizationRole enum with a round-trip', function () {
    $member = createMember();

    expect($member->role)->toBeInstanceOf(OrganizationRole::class)
        ->and($member->role)->toBe(OrganizationRole::Operator)
        ->and($member->getRawOriginal('role'))->toBe('operator');
});

it('belongs to an organization and a user', function () {
    $member = createMember();

    expect($member->organization)->toBeInstanceOf(Organization::class)
        ->and($member->user)->toBeInstanceOf(User::class);
});

it('rejects duplicate (organization_id, user_id) pairs', function () {
    $organization = Organization::create(['name' => 'Acme', 'slug' => 'acme']);
    $user = createUser();

    OrganizationMember::create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role' => 'operator',
    ]);

    OrganizationMember::create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role' => 'operator',
    ]);
})->throws(QueryException::class);

function createMember(): OrganizationMember
{
    $organization = Organization::create(['name' => 'Acme', 'slug' => 'acme']);

    return OrganizationMember::create([
        'organization_id' => $organization->id,
        'user_id' => createUser()->id,
        'role' => 'operator',
    ]);
}

function createUser(): User
{
    return User::create([
        'name' => 'Dev',
        'email' => fake()->unique()->safeEmail(),
        'password' => bcrypt('secret'),
    ]);
}
