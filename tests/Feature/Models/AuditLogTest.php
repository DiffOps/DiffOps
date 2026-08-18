<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('generates a uuid primary key on creation', function () {
    $log = createAuditLogRecord();

    expect($log->id)->toBeString()
        ->and(strlen($log->id))->toBe(36)
        ->and(AuditLog::query()->whereKey($log->id)->exists())->toBeTrue();
});

it('accepts the audit entry through mass assignment', function () {
    $entityId = (string) Str::uuid();

    $log = createAuditLogRecord([
        'action' => 'org.settings.updated',
        'entity_type' => 'organization',
        'entity_id' => $entityId,
        'payload' => ['key' => 'comment_on_pr', 'value' => true],
    ]);

    expect($log->action)->toBe('org.settings.updated')
        ->and($log->entity_type)->toBe('organization')
        ->and($log->entity_id)->toBe($entityId)
        ->and($log->payload)->toBe(['key' => 'comment_on_pr', 'value' => true]);
});

it('casts payload to array with raw round-trip', function () {
    $log = createAuditLogRecord(['payload' => ['finding' => 'hardcoded_secret', 'count' => 3]]);

    expect($log->payload)->toBe(['finding' => 'hardcoded_secret', 'count' => 3])
        ->and(json_decode($log->getRawOriginal('payload'), true))
        ->toBe(['finding' => 'hardcoded_secret', 'count' => 3]);
});

it('belongs to the actor user', function () {
    $log = createAuditLogRecord();

    expect($log->user)->toBeInstanceOf(User::class);
});

it('allows a null user id and returns a null user relation', function () {
    $log = createAuditLogRecord(['user_id' => null]);

    expect($log->user_id)->toBeNull()
        ->and($log->user)->toBeNull();
});

it('keeps entity_id as a plain uuid string without cast', function () {
    $entityId = (string) Str::uuid();

    $log = createAuditLogRecord(['entity_id' => $entityId]);

    expect($log->entity_id)->toBeString()
        ->and(strlen($log->entity_id))->toBe(36)
        ->and($log->getRawOriginal('entity_id'))->toBe($entityId);
});

it('is append-only: updated_at is disabled', function () {
    $log = createAuditLogRecord();

    expect($log->getUpdatedAtColumn())->toBeNull()
        ->and(Schema::hasColumn('audit_logs', 'updated_at'))->toBeFalse();
});

function createAuditLogRecord(array $overrides = []): AuditLog
{
    $user = User::create([
        'name' => 'Dev',
        'email' => 'dev@acme.test',
        'password' => bcrypt('secret'),
    ]);

    return AuditLog::create(array_merge([
        'user_id' => $user->id,
        'action' => 'pr.reviewed',
        'entity_type' => 'pull_request',
        'entity_id' => (string) Str::uuid(),
        'payload' => ['verdict' => 'clear', 'score' => 87],
    ], $overrides));
}
