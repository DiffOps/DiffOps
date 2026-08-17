<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

const CORE_TABLES = [
    'organizations',
    'organization_members',
    'pull_requests',
    'pull_request_files',
    'risk_assessments',
    'ai_decisions',
];

it('uses snake_case for every column across the core tables', function () {
    foreach (CORE_TABLES as $table) {
        $columns = array_column(Schema::getColumns($table), 'name');

        foreach ($columns as $column) {
            expect($column)->toMatch('/^[a-z0-9_]+$/');
        }
    }
});

it('orders the 2026_08_17 core migrations after the scaffold migrations', function () {
    $files = array_map('basename', glob(database_path('migrations/*.php')));
    $scaffold = array_map('basename', glob(database_path('migrations/0001_01_01_*.php')));
    $core = array_map('basename', glob(database_path('migrations/2026_08_17_*.php')));

    expect(count($scaffold))->toBe(3);

    $firstCoreIndex = array_search($core[0], $files, true);
    $lastScaffoldIndex = array_search($scaffold[2], $files, true);

    expect($firstCoreIndex)->toBeGreaterThan($lastScaffoldIndex);
});

it('keeps migrations free of raw pgsql and DB::raw calls', function () {
    $forbidden = ['ENABLE ROW LEVEL SECURITY', 'CREATE POLICY', 'CREATE TRIGGER', 'gen_random_uuid', 'DB::raw('];

    foreach (glob(database_path('migrations/*.php')) as $file) {
        foreach ($forbidden as $needle) {
            expect(file_get_contents($file))->not->toContain($needle);
        }
    }
});

it('requires every DB::statement call to be guarded by a driver check', function () {
    foreach (glob(database_path('migrations/*.php')) as $file) {
        $lines = file($file);

        foreach ($lines as $lineNumber => $line) {
            if (! str_contains($line, 'DB::statement(')) {
                continue;
            }

            // Look back over the enclosing block: the statement must sit
            // inside a driver branch (e.g. `if ($driver === 'pgsql')`).
            $window = implode("\n", array_slice($lines, max(0, $lineNumber - 8), $lineNumber));

            expect(str_contains($window, 'if ($driver'))->toBeTrue();
        }
    }
});

it('keeps migrations free of secrets', function () {
    $forbidden = ['DB_PASSWORD', 'SUPABASE_SERVICE_ROLE', 'sk-', 'ghp_', 'PRIVATE KEY'];

    foreach (glob(database_path('migrations/*.php')) as $file) {
        foreach ($forbidden as $needle) {
            expect(file_get_contents($file))->not->toContain($needle);
        }
    }
});

it('reserves the supabase migrations directory with a README', function () {
    expect(file_exists(base_path('supabase/migrations/README.md')))->toBeTrue();
});

it('rolls back all core migrations while keeping scaffold tables intact', function () {
    Artisan::call('migrate:rollback', ['--step' => 7]);

    foreach (CORE_TABLES as $table) {
        expect(Schema::hasTable($table))->toBeFalse();
    }

    expect(Schema::hasTable('users'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'supabase_uid'))->toBeFalse()
        ->and(Schema::hasTable('sessions'))->toBeTrue()
        ->and(Schema::hasTable('cache'))->toBeTrue()
        ->and(Schema::hasTable('jobs'))->toBeTrue();
});
