<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('keeps the scaffold user columns intact', function () {
    $columns = array_column(Schema::getColumns('users'), 'name');

    expect($columns)->toContain(
        'id', 'name', 'email', 'email_verified_at', 'password', 'remember_token', 'created_at', 'updated_at'
    );
});

it('adds the tactical profile columns', function () {
    $columns = array_column(Schema::getColumns('users'), 'name');

    expect($columns)->toContain(
        'supabase_uid', 'github_username', 'avatar_url', 'is_commander', 'preferences', 'last_login_at'
    );
});

it('defaults is_commander to false', function () {
    DB::table('users')->insert([
        'name' => 'Operator',
        'email' => 'operator@diffops.test',
        'password' => bcrypt('secret'),
    ]);

    $user = DB::table('users')->where('email', 'operator@diffops.test')->first();

    expect((bool) $user->is_commander)->toBeFalse();
});

it('allows multiple NULL supabase_uid but rejects duplicates', function () {
    DB::table('users')->insert([
        ['name' => 'A', 'email' => 'a@diffops.test', 'password' => 'x', 'supabase_uid' => null],
        ['name' => 'B', 'email' => 'b@diffops.test', 'password' => 'x', 'supabase_uid' => null],
    ]);

    expect(DB::table('users')->count())->toBe(2);

    DB::table('users')->insert([
        'name' => 'C',
        'email' => 'c@diffops.test',
        'password' => 'x',
        'supabase_uid' => '6d1c8d5e-0000-4000-8000-000000000001',
    ]);
    DB::table('users')->insert([
        'name' => 'D',
        'email' => 'd@diffops.test',
        'password' => 'x',
        'supabase_uid' => '6d1c8d5e-0000-4000-8000-000000000001',
    ]);
})->throws(QueryException::class);

it('round-trips the preferences json column', function () {
    DB::table('users')->insert([
        'name' => 'Analyst',
        'email' => 'analyst@diffops.test',
        'password' => 'x',
        'preferences' => json_encode(['theme' => 'dark', 'digest' => ['monday', 'friday']]),
    ]);

    $user = DB::table('users')->where('email', 'analyst@diffops.test')->first();
    $preferences = json_decode($user->preferences, true);

    expect($preferences)->toBe(['theme' => 'dark', 'digest' => ['monday', 'friday']]);
});

it('indexes github_username', function () {
    expect(Schema::hasIndex('users', 'users_github_username_index'))->toBeTrue();
});
