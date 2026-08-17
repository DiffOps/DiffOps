<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates the organizations table', function () {
    expect(Schema::hasTable('organizations'))->toBeTrue();
});

it('has the expected columns', function () {
    $columns = Schema::getColumns('organizations');

    expect($columns)->not->toBeEmpty()
        ->and(array_column($columns, 'name'))->toContain(
            'id', 'name', 'slug', 'created_at', 'updated_at'
        );
});

it('uses a uuid primary key (string on sqlite, uuid on pgsql)', function () {
    $idType = Schema::getColumnType('organizations', 'id');
    $driver = Schema::getConnection()->getDriverName();

    if ($driver === 'pgsql') {
        expect($idType)->toBe('uuid');
    } else {
        expect($idType)->toBe('varchar');
    }
});

it('defines name and slug as string columns', function () {
    expect(Schema::getColumnType('organizations', 'name'))->toBe('varchar')
        ->and(Schema::getColumnType('organizations', 'slug'))->toBe('varchar');
});

it('enforces a unique slug (second insert throws)', function () {
    $now = now();

    DB::table('organizations')->insert([
        'id' => (string) Str::uuid(),
        'name' => 'Tactical Unit',
        'slug' => 'tactical-unit',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('organizations')->insert([
        'id' => (string) Str::uuid(),
        'name' => 'Tactical Unit 2',
        'slug' => 'tactical-unit',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
})->throws(QueryException::class);

it('persists and reads back organization rows', function () {
    $now = now();
    $id = (string) Str::uuid();

    DB::table('organizations')->insert([
        'id' => $id,
        'name' => 'Acme Corp',
        'slug' => 'acme-corp',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $row = DB::table('organizations')->where('slug', 'acme-corp')->first();

    expect($row)->not->toBeNull()
        ->and($row->id)->toBe($id)
        ->and($row->name)->toBe('Acme Corp')
        ->and($row->created_at)->not->toBeNull();
});
