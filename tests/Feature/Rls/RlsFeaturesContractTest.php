<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

const RLS_FEATURE_ENABLED_TABLES = [
    'repositories',
    'report_comments',
    'audit_logs',
    'contributor_risks',
    'repo_watchlist',
];

const RLS_FEATURE_POLICIES = [
    'repositories_select' => 'repositories',
    'report_comments_select' => 'report_comments',
    'audit_logs_select' => 'audit_logs',
    'contributor_risks_select' => 'contributor_risks',
    'repo_watchlist_select' => 'repo_watchlist',
];

it('enables row level security on all five feature tables', function () {
    $path = database_path('migrations/2026_08_17_000206_enable_rls_on_feature_tables.php');
    expect(file_exists($path))->toBeTrue();

    $migration = file_get_contents($path);

    foreach (RLS_FEATURE_ENABLED_TABLES as $table) {
        expect($migration)->toContain("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
    }
})->group('rls');

it('creates the five select policies with the expected names', function () {
    $path = database_path('migrations/2026_08_17_000206_enable_rls_on_feature_tables.php');
    expect(file_exists($path))->toBeTrue();

    $migration = file_get_contents($path);

    foreach (array_keys(RLS_FEATURE_POLICIES) as $policy) {
        expect($migration)->toContain("CREATE POLICY \"{$policy}\"");
    }
})->group('rls');

it('keeps every feature policy name under 63 characters', function () {
    foreach (array_keys(RLS_FEATURE_POLICIES) as $policy) {
        expect(strlen($policy))->toBeLessThan(63);
    }
})->group('rls');

it('drops every policy and disables rls on rollback', function () {
    $path = database_path('migrations/2026_08_17_000206_enable_rls_on_feature_tables.php');
    expect(file_exists($path))->toBeTrue();

    $migration = file_get_contents($path);

    foreach (RLS_FEATURE_POLICIES as $policy => $table) {
        expect($migration)->toContain("DROP POLICY IF EXISTS \"{$policy}\" ON {$table}");
    }

    foreach (RLS_FEATURE_ENABLED_TABLES as $table) {
        expect($migration)->toContain("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
    }
})->group('rls');

it('routes feature policies through supabase_uid and never through user_id = auth.uid()', function () {
    $path = database_path('migrations/2026_08_17_000206_enable_rls_on_feature_tables.php');
    expect(file_exists($path))->toBeTrue();

    $migration = file_get_contents($path);

    expect($migration)->toContain('auth.uid()')
        ->and($migration)->toContain('supabase_uid')
        ->and($migration)->not->toContain('user_id = auth.uid()');
})->group('rls');

it('creates select-only policies for the authenticated role', function () {
    $path = database_path('migrations/2026_08_17_000206_enable_rls_on_feature_tables.php');
    expect(file_exists($path))->toBeTrue();

    $migration = file_get_contents($path);

    expect($migration)->toContain('FOR SELECT TO authenticated')
        ->and($migration)->not->toContain('FOR INSERT')
        ->and($migration)->not->toContain('FOR UPDATE')
        ->and($migration)->not->toContain('FOR DELETE');
})->group('rls');

it('guards the feature rls migration with a pgsql driver check', function () {
    $path = database_path('migrations/2026_08_17_000206_enable_rls_on_feature_tables.php');
    expect(file_exists($path))->toBeTrue();

    $migration = file_get_contents($path);

    expect($migration)->toContain('getDriverName()')
        ->and($migration)->toContain("!== 'pgsql'");
})->group('rls');

it('migrates cleanly with the feature rls guard on non-pgsql drivers', function () {
    // On sqlite the guard returns early, so the feature tables must still exist.
    expect(Schema::hasTable('repositories'))->toBeTrue()
        ->and(Schema::hasTable('repo_watchlist'))->toBeTrue();
})->group('rls');
