<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('reports the commander flag', function () {
    $commander = User::create([
        'name' => 'Cmdr',
        'email' => 'cmdr@diffops.test',
        'password' => 'secret',
        'is_commander' => true,
    ]);
    $operator = User::create([
        'name' => 'Operator',
        'email' => 'operator@diffops.test',
        'password' => 'secret',
        'is_commander' => false,
    ]);

    expect($commander->isCommander())->toBeTrue()
        ->and($operator->isCommander())->toBeFalse();
});

it('checks membership in an organization', function () {
    $org = Organization::create(['name' => 'Ops Unit', 'slug' => 'ops-unit']);
    $member = User::create([
        'name' => 'Member',
        'email' => 'member@diffops.test',
        'password' => 'secret',
    ]);
    $outsider = User::create([
        'name' => 'Outsider',
        'email' => 'outsider@diffops.test',
        'password' => 'secret',
    ]);

    OrganizationMember::create([
        'organization_id' => $org->id,
        'user_id' => $member->id,
        'role' => OrganizationRole::Operator,
    ]);

    expect($member->isMemberOf($org))->toBeTrue()
        ->and($outsider->isMemberOf($org))->toBeFalse();
});

it('resolves the tactical role of a member', function () {
    $org = Organization::create(['name' => 'Ops Unit', 'slug' => 'ops-unit']);
    $commander = User::create([
        'name' => 'Commander',
        'email' => 'commander@diffops.test',
        'password' => 'secret',
    ]);
    $operator = User::create([
        'name' => 'Operator',
        'email' => 'operator@diffops.test',
        'password' => 'secret',
    ]);

    OrganizationMember::create([
        'organization_id' => $org->id,
        'user_id' => $commander->id,
        'role' => OrganizationRole::Commander,
    ]);
    OrganizationMember::create([
        'organization_id' => $org->id,
        'user_id' => $operator->id,
        'role' => OrganizationRole::Operator,
    ]);

    expect($commander->roleIn($org))->toBe(OrganizationRole::Commander)
        ->and($operator->roleIn($org))->toBe(OrganizationRole::Operator);
});

it('returns null role without membership', function () {
    $org = Organization::create(['name' => 'Ops Unit', 'slug' => 'ops-unit']);
    $outsider = User::create([
        'name' => 'Outsider',
        'email' => 'outsider@diffops.test',
        'password' => 'secret',
    ]);

    expect($outsider->roleIn($org))->toBeNull();
});
