<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('adds is_active defaulting to true', function () {
    expect(Schema::hasColumn('users', 'is_active'))->toBeTrue();

    DB::table('users')->insert([
        'name' => 'Active',
        'email' => 'active@diffops.test',
        'password' => bcrypt('secret'),
    ]);

    $user = DB::table('users')->where('email', 'active@diffops.test')->first();

    expect((bool) $user->is_active)->toBeTrue();
});

it('persists is_active false as a raw zero and casts to false', function () {
    DB::table('users')->insert([
        'name' => 'Inactive',
        'email' => 'inactive@diffops.test',
        'password' => bcrypt('secret'),
        'is_active' => 0,
    ]);

    $raw = DB::table('users')->where('email', 'inactive@diffops.test')->first();

    expect((int) $raw->is_active)->toBe(0);

    $user = User::where('email', 'inactive@diffops.test')->first();

    expect($user->is_active)->toBeFalse();
});

it('keeps the tactical profile columns intact', function () {
    $columns = array_column(Schema::getColumns('users'), 'name');

    expect($columns)->toContain(
        'supabase_uid', 'github_username', 'avatar_url', 'is_commander', 'preferences', 'last_login_at'
    );
});

it('removes is_active on rollback', function () {
    $migration = require database_path('migrations/2026_08_17_000111_add_is_active_to_users_table.php');

    $migration->down();

    expect(Schema::hasColumn('users', 'is_active'))->toBeFalse();
});
