<?php

declare(strict_types=1);

it('defaults the database connection to pgsql in app env', function (): void {
    // O phpunit.xml força DB_CONNECTION=sqlite no ambiente de teste. Para validar
    // o fallback do config no ambiente de aplicação (APP_ENV=local), removemos
    // temporariamente o override e relemos o arquivo de configuração.
    $saved = [
        'env' => $_ENV['DB_CONNECTION'] ?? null,
        'server' => $_SERVER['DB_CONNECTION'] ?? null,
        'system' => getenv('DB_CONNECTION') ?: false,
    ];

    unset($_ENV['DB_CONNECTION'], $_SERVER['DB_CONNECTION']);
    putenv('DB_CONNECTION');

    try {
        $config = require base_path('config/database.php');

        expect($config['default'])->toBe('pgsql');
    } finally {
        unset($_ENV['DB_CONNECTION'], $_SERVER['DB_CONNECTION']);
        putenv('DB_CONNECTION');

        if ($saved['env'] !== null) {
            $_ENV['DB_CONNECTION'] = $saved['env'];
        }

        if ($saved['server'] !== null) {
            $_SERVER['DB_CONNECTION'] = $saved['server'];
        }

        if ($saved['system'] !== false) {
            putenv("DB_CONNECTION={$saved['system']}");
        }
    }
});

it('forces sqlite in-memory in the test suite', function (): void {
    expect(config('database.default'))->toBe('sqlite')
        ->and(config('database.connections.sqlite.database'))->toBe(':memory:');
});

it('targets the supabase postgres host', function (): void {
    if (config('database.default') !== 'pgsql') {
        $this->markTestSkipped('PostgreSQL not configured as default connection');
    }
    expect(config('database.connections.pgsql.host'))->toContain('supabase.co');
});

it('requires ssl on the pgsql connection', function (): void {
    if (config('database.default') !== 'pgsql') {
        $this->markTestSkipped('PostgreSQL not configured as default connection');
    }
    expect(config('database.connections.pgsql.sslmode'))->toBe('require');
});

it('keeps the test environment offline', function (): void {
    expect(config('queue.default'))->toBe('sync')
        ->and(config('cache.default'))->toBe('array');
});
