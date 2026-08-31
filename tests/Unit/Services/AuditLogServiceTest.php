<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogService;
use Database\Factories\AuditLogFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('persists an audit log row via the service', function (): void {
    // Foreign key on audit_logs.user_id → users.id requires a real user.
    User::factory()->create(['id' => 42]);

    $log = app(AuditLogService::class)->log(
        'repository.registered',
        'repository',
        42,
        '11111111-1111-1111-1111-111111111111',
        ['full_name' => 'acme/web'],
    );

    expect($log)->toBeInstanceOf(AuditLog::class)
        ->and(AuditLog::count())->toBe(1);

    $stored = AuditLog::first();
    expect($stored->action)->toBe('repository.registered')
        ->and($stored->entity_type)->toBe('repository')
        ->and($stored->user_id)->toBe(42)
        ->and($stored->entity_id)->toBe('11111111-1111-1111-1111-111111111111')
        ->and($stored->payload)->toBe(['full_name' => 'acme/web']);
});

it('masks payload values whose keys match secret patterns', function (): void {
    $service = new AuditLogService;

    $sanitized = $service->sanitize([
        'api_key' => 'super-secret',
        'access_token' => 'abc',
        'client_secret' => 'x',
        'password' => 'hunter2',
        'authorization' => 'Bearer xyz',
        'private_key' => '--- PEM ---',
        'passwd' => 'qwerty',
    ]);

    expect($sanitized)->toBe([
        'api_key' => '[REDACTED]',
        'access_token' => '[REDACTED]',
        'client_secret' => '[REDACTED]',
        'password' => '[REDACTED]',
        'authorization' => '[REDACTED]',
        'private_key' => '[REDACTED]',
        'passwd' => '[REDACTED]',
    ]);
});

it('masks payload values matching secret value patterns', function (): void {
    $service = new AuditLogService;

    $sanitized = $service->sanitize([
        'note' => 'ghp_abcdef1234567890',
        'key2' => 'sk-abcdef1234567890',
        'jwt' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9',
        'aws' => 'AKIAIOSFODNN7EXAMPLE',
        'slack' => 'xoxb-12345-67890-abcdef',
        'plain' => 'just a normal value',
    ]);

    expect($sanitized['note'])->toBe('[REDACTED]')
        ->and($sanitized['key2'])->toBe('[REDACTED]')
        ->and($sanitized['jwt'])->toBe('[REDACTED]')
        ->and($sanitized['aws'])->toBe('[REDACTED]')
        ->and($sanitized['slack'])->toBe('[REDACTED]')
        ->and($sanitized['plain'])->toBe('just a normal value');
});

it('recurses into nested arrays when sanitizing', function (): void {
    $service = new AuditLogService;

    $sanitized = $service->sanitize([
        'level1' => [
            'password' => 'hidden',
            'visible' => 'ok',
            'level2' => [
                'token' => 'nope',
            ],
        ],
    ]);

    expect($sanitized['level1']['password'])->toBe('[REDACTED]')
        ->and($sanitized['level1']['visible'])->toBe('ok')
        ->and($sanitized['level1']['level2']['token'])->toBe('[REDACTED]');
});

it('preserves int, bool and null payload values', function (): void {
    $service = new AuditLogService;

    $sanitized = $service->sanitize([
        'count' => 5,
        'active' => true,
        'missing' => null,
        'label' => 'visible',
    ]);

    expect($sanitized)->toBe([
        'count' => 5,
        'active' => true,
        'missing' => null,
        'label' => 'visible',
    ]);
});

it('accepts a null user and logs a system-initiated action', function (): void {
    $log = app(AuditLogService::class)->log(
        'webhook.received',
        'pull_request',
        null,
        null,
        ['event' => 'pull_request'],
    );

    expect($log->user_id)->toBeNull()
        ->and(AuditLog::first()->user_id)->toBeNull();
});

it('builds rows through the factory', function (): void {
    $log = AuditLogFactory::new()->create(['action' => 'factory.test']);

    expect($log)->toBeInstanceOf(AuditLog::class)
        ->and($log->action)->toBe('factory.test')
        ->and(AuditLog::count())->toBe(1);
});
