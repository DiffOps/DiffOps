<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates organization_members with the expected columns', function () {
    expect(Schema::hasTable('organization_members'))->toBeTrue();

    $columns = array_column(Schema::getColumns('organization_members'), 'name');

    expect($columns)->toContain(
        'id', 'organization_id', 'user_id', 'role', 'created_at', 'updated_at'
    );
});

it('defaults role to operator', function () {
    [$orgId, $userId] = seedOrganizationAndUser();

    DB::table('organization_members')->insert([
        'id' => (string) Str::uuid(),
        'organization_id' => $orgId,
        'user_id' => $userId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $member = DB::table('organization_members')->where('organization_id', $orgId)->first();

    expect($member->role)->toBe('operator');
});

it('rejects a member for a non-existent organization', function () {
    $userId = seedUser();

    DB::table('organization_members')->insert([
        'id' => (string) Str::uuid(),
        'organization_id' => (string) Str::uuid(),
        'user_id' => $userId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
})->throws(QueryException::class);

it('rejects duplicate (organization_id, user_id) pairs', function () {
    [$orgId, $userId] = seedOrganizationAndUser();
    $now = now();

    foreach ([1, 2] as $i) {
        DB::table('organization_members')->insert([
            'id' => (string) Str::uuid(),
            'organization_id' => $orgId,
            'user_id' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
})->throws(QueryException::class);

it('cascades members when the organization is deleted', function () {
    [$orgId, $userId] = seedOrganizationAndUser();

    DB::table('organization_members')->insert([
        'id' => (string) Str::uuid(),
        'organization_id' => $orgId,
        'user_id' => $userId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('organizations')->where('id', $orgId)->delete();

    expect(DB::table('organization_members')->where('user_id', $userId)->count())->toBe(0);
});

it('cascades members when the user is deleted', function () {
    [$orgId, $userId] = seedOrganizationAndUser();

    DB::table('organization_members')->insert([
        'id' => (string) Str::uuid(),
        'organization_id' => $orgId,
        'user_id' => $userId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('users')->where('id', $userId)->delete();

    expect(DB::table('organization_members')->where('organization_id', $orgId)->count())->toBe(0);
});

function seedOrganizationAndUser(): array
{
    $orgId = (string) Str::uuid();
    $userId = seedUser();

    DB::table('organizations')->insert([
        'id' => $orgId,
        'name' => 'Ops Unit',
        'slug' => 'ops-unit',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [$orgId, $userId];
}

function seedUser(): int
{
    $id = DB::table('users')->insertGetId([
        'name' => 'Operator',
        'email' => 'operator-'.Str::uuid().'@diffops.test',
        'password' => 'x',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}
