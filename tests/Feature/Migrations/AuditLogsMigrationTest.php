<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates audit_logs with the expected columns', function () {
    expect(Schema::hasTable('audit_logs'))->toBeTrue();

    $columns = array_column(Schema::getColumns('audit_logs'), 'name');

    expect($columns)->toContain(
        'id', 'user_id', 'action', 'entity_type', 'entity_id', 'payload', 'created_at'
    );
});

it('is append-only: created_at exists and updated_at does not', function () {
    expect(Schema::hasColumn('audit_logs', 'created_at'))->toBeTrue()
        ->and(Schema::hasColumn('audit_logs', 'updated_at'))->toBeFalse();
});

it('round-trips the payload json column', function () {
    $userId = seedUserForAudit();

    DB::table('audit_logs')->insert([
        'id' => (string) Str::uuid(),
        'user_id' => $userId,
        'action' => 'assessment.created',
        'entity_type' => 'risk_assessment',
        'entity_id' => (string) Str::uuid(),
        'payload' => json_encode(['verdict' => 'flagged', 'score' => 42]),
        'created_at' => now(),
    ]);

    $log = DB::table('audit_logs')->where('action', 'assessment.created')->first();

    expect(json_decode($log->payload, true))->toBe(['verdict' => 'flagged', 'score' => 42]);
});

it('rejects an audit log for a non-existent user', function () {
    DB::table('audit_logs')->insert([
        'id' => (string) Str::uuid(),
        'user_id' => 999999,
        'action' => 'assessment.created',
        'entity_type' => 'risk_assessment',
        'created_at' => now(),
    ]);
})->throws(QueryException::class);

it('sets user_id to null when the user is deleted', function () {
    $userId = seedUserForAudit();

    DB::table('audit_logs')->insert([
        'id' => (string) Str::uuid(),
        'user_id' => $userId,
        'action' => 'assessment.created',
        'entity_type' => 'risk_assessment',
        'created_at' => now(),
    ]);

    DB::table('users')->where('id', $userId)->delete();

    $log = DB::table('audit_logs')->where('action', 'assessment.created')->first();

    expect(DB::table('audit_logs')->count())->toBe(1)
        ->and($log->user_id)->toBeNull();
});

it('keeps entity_id and payload null by default', function () {
    $userId = seedUserForAudit();

    DB::table('audit_logs')->insert([
        'id' => (string) Str::uuid(),
        'user_id' => $userId,
        'action' => 'user.login',
        'entity_type' => 'session',
        'created_at' => now(),
    ]);

    $log = DB::table('audit_logs')->where('action', 'user.login')->first();

    expect($log->entity_id)->toBeNull()
        ->and($log->payload)->toBeNull();
});

function seedUserForAudit(): int
{
    $id = DB::table('users')->insertGetId([
        'name' => 'Audit Operator',
        'email' => 'audit@example.com',
        'password' => bcrypt('password'),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}
