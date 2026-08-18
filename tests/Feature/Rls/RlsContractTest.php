<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

defined('RLS_MIGRATIONS') || define('RLS_MIGRATIONS', [
    '2026_08_17_000108_enable_rls_on_core_tables.php',
    '2026_08_17_000109_add_append_only_and_membership_triggers.php',
    '2026_08_17_000110_enable_realtime_publication_for_incursions.php',
    '2026_08_17_000206_enable_rls_on_feature_tables.php',
]);

const RLS_ENABLED_TABLES = [
    'organizations',
    'organization_members',
    'pull_requests',
    'pull_request_files',
    'risk_assessments',
    'ai_decisions',
    'users',
];

const RLS_POLICIES = [
    'organizations_select' => 'organizations',
    'organization_members_select' => 'organization_members',
    'pull_requests_select' => 'pull_requests',
    'pull_request_files_select' => 'pull_request_files',
    'risk_assessments_select' => 'risk_assessments',
    'ai_decisions_select' => 'ai_decisions',
    'users_select_self' => 'users',
];

it('enables row level security on all seven core tables', function () {
    $path = database_path('migrations/2026_08_17_000108_enable_rls_on_core_tables.php');
    expect(file_exists($path))->toBeTrue();

    $migration = file_get_contents($path);

    foreach (RLS_ENABLED_TABLES as $table) {
        expect($migration)->toContain("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
    }
})->group('rls');

it('creates the seven select policies with the expected names', function () {
    $path = database_path('migrations/2026_08_17_000108_enable_rls_on_core_tables.php');
    expect(file_exists($path))->toBeTrue();

    $migration = file_get_contents($path);

    foreach (array_keys(RLS_POLICIES) as $policy) {
        expect($migration)->toContain("CREATE POLICY \"{$policy}\"");
    }
})->group('rls');

it('keeps every policy name under 63 characters', function () {
    foreach (array_keys(RLS_POLICIES) as $policy) {
        expect(strlen($policy))->toBeLessThan(63);
    }
})->group('rls');

it('drops every policy and disables rls on rollback', function () {
    $path = database_path('migrations/2026_08_17_000108_enable_rls_on_core_tables.php');
    expect(file_exists($path))->toBeTrue();

    $migration = file_get_contents($path);

    foreach (RLS_POLICIES as $policy => $table) {
        expect($migration)->toContain("DROP POLICY IF EXISTS \"{$policy}\" ON {$table}");
    }

    foreach (RLS_ENABLED_TABLES as $table) {
        expect($migration)->toContain("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
    }
})->group('rls');

it('keeps every trigger and function name under 63 characters', function () {
    $path = database_path('migrations/2026_08_17_000109_add_append_only_and_membership_triggers.php');
    expect(file_exists($path))->toBeTrue();

    $migration = file_get_contents($path);

    $names = [
        'fn_block_ai_decisions_write',
        'trg_ai_decisions_append_only',
        'fn_membership_touch_updated_at',
        'trg_membership_touch_updated_at',
    ];

    foreach ($names as $name) {
        expect(strlen($name))->toBeLessThan(63)
            ->and($migration)->toContain($name);
    }
})->group('rls');

it('blocks ai_decisions writes with an append-only trigger', function () {
    $path = database_path('migrations/2026_08_17_000109_add_append_only_and_membership_triggers.php');
    expect(file_exists($path))->toBeTrue();

    $migration = file_get_contents($path);

    expect($migration)->toContain('BEFORE UPDATE OR DELETE')
        ->and($migration)->toContain('RAISE EXCEPTION')
        ->and($migration)->toContain("'ai_decisions is append-only'");
})->group('rls');

it('touches updated_at on organization_members through a trigger', function () {
    $path = database_path('migrations/2026_08_17_000109_add_append_only_and_membership_triggers.php');
    expect(file_exists($path))->toBeTrue();

    $migration = file_get_contents($path);

    expect($migration)->toContain('BEFORE UPDATE ON organization_members')
        ->and($migration)->toContain('NEW.updated_at := now()');
})->group('rls');

it('adds incursion tables to the supabase realtime publication', function () {
    $path = database_path('migrations/2026_08_17_000110_enable_realtime_publication_for_incursions.php');
    expect(file_exists($path))->toBeTrue();

    $migration = file_get_contents($path);

    expect($migration)->toContain('supabase_realtime')
        ->and($migration)->toContain('pull_requests')
        ->and($migration)->toContain('risk_assessments')
        ->and($migration)->toContain('pg_publication');
})->group('rls');

it('removes the tables from the realtime publication on rollback', function () {
    $path = database_path('migrations/2026_08_17_000110_enable_realtime_publication_for_incursions.php');
    expect(file_exists($path))->toBeTrue();

    $migration = file_get_contents($path);

    expect($migration)->toContain('ALTER PUBLICATION supabase_realtime DROP TABLE pull_requests')
        ->and($migration)->toContain('ALTER PUBLICATION supabase_realtime DROP TABLE risk_assessments');
})->group('rls');

it('guards every rls migration with a pgsql driver check', function () {
    $guarded = ['getDriverName()', "!== 'pgsql'"];

    foreach (RLS_MIGRATIONS as $name) {
        $path = database_path("migrations/{$name}");

        if (! file_exists($path)) {
            continue;
        }

        $migration = file_get_contents($path);

        foreach ($guarded as $needle) {
            expect($migration)->toContain($needle);
        }
    }
})->group('rls');

it('routes policies through supabase_uid and never through user_id = auth.uid()', function () {
    $path = database_path('migrations/2026_08_17_000108_enable_rls_on_core_tables.php');
    expect(file_exists($path))->toBeTrue();

    $migration = file_get_contents($path);

    expect($migration)->toContain('auth.uid()')
        ->and($migration)->toContain('supabase_uid')
        ->and($migration)->not->toContain('user_id = auth.uid()');
})->group('rls');

it('creates select-only policies for the authenticated role', function () {
    $path = database_path('migrations/2026_08_17_000108_enable_rls_on_core_tables.php');
    expect(file_exists($path))->toBeTrue();

    $migration = file_get_contents($path);

    expect($migration)->toContain('FOR SELECT TO authenticated')
        ->and($migration)->not->toContain('FOR INSERT')
        ->and($migration)->not->toContain('FOR UPDATE')
        ->and($migration)->not->toContain('FOR DELETE');
})->group('rls');

it('migrates cleanly with the rls guard on non-pgsql drivers', function () {
    // On sqlite the guard returns early, so the core tables must still exist.
    expect(Schema::hasTable('pull_requests'))->toBeTrue();
})->group('rls');
