<?php

declare(strict_types=1);

it('keeps the github config block free of secrets without env', function (): void {
    $saved = githubConfigSaveEnv();

    try {
        githubConfigUnsetEnv();

        $config = require base_path('config/services.php');

        expect($config['github']['api_url'])->toBe('https://api.github.com')
            ->and($config['github']['app_id'])->toBeNull()
            ->and($config['github']['app_private_key'])->toBeNull()
            ->and($config['github']['webhook_secret'])->toBeNull();
    } finally {
        githubConfigRestoreEnv($saved);
    }
});

it('defaults the github timeout, token cache ttl and retries', function (): void {
    $saved = githubConfigSaveEnv();

    try {
        githubConfigUnsetEnv();

        $config = require base_path('config/services.php');

        expect($config['github']['timeout'])->toBe(15)
            ->and($config['github']['token_cache_ttl'])->toBe(3300)
            ->and($config['github']['retries'])->toBe(2);
    } finally {
        githubConfigRestoreEnv($saved);
    }
});

it('reads the github config values from the env', function (): void {
    $saved = githubConfigSaveEnv();

    try {
        githubConfigUnsetEnv();

        $_ENV['GITHUB_API_URL'] = 'https://api.github.example.com';
        $_ENV['GITHUB_APP_ID'] = '123456';
        $_ENV['GITHUB_APP_PRIVATE_KEY'] = '-----BEGIN RSA PRIVATE KEY-----';
        $_ENV['GITHUB_WEBHOOK_SECRET'] = 'a-webhook-secret';

        $config = require base_path('config/services.php');

        expect($config['github']['api_url'])->toBe('https://api.github.example.com')
            ->and($config['github']['app_id'])->toBe('123456')
            ->and($config['github']['app_private_key'])->toBe('-----BEGIN RSA PRIVATE KEY-----')
            ->and($config['github']['webhook_secret'])->toBe('a-webhook-secret');
    } finally {
        githubConfigRestoreEnv($saved);
    }
});

it('parses the github numeric env values as integers', function (): void {
    $saved = githubConfigSaveEnv();

    try {
        githubConfigUnsetEnv();

        $_ENV['GITHUB_TIMEOUT'] = '20';
        $_ENV['GITHUB_TOKEN_CACHE_TTL'] = '4500';
        $_ENV['GITHUB_RETRIES'] = '5';

        $config = require base_path('config/services.php');

        expect($config['github']['timeout'])->toBe(20)
            ->and($config['github']['token_cache_ttl'])->toBe(4500)
            ->and($config['github']['retries'])->toBe(5);
    } finally {
        githubConfigRestoreEnv($saved);
    }
});

function githubConfigSaveEnv(): array
{
    return [
        'api_url' => githubConfigReadEnv('GITHUB_API_URL'),
        'app_id' => githubConfigReadEnv('GITHUB_APP_ID'),
        'private_key' => githubConfigReadEnv('GITHUB_APP_PRIVATE_KEY'),
        'webhook_secret' => githubConfigReadEnv('GITHUB_WEBHOOK_SECRET'),
        'timeout' => githubConfigReadEnv('GITHUB_TIMEOUT'),
        'ttl' => githubConfigReadEnv('GITHUB_TOKEN_CACHE_TTL'),
        'retries' => githubConfigReadEnv('GITHUB_RETRIES'),
    ];
}

function githubConfigReadEnv(string $name): array
{
    return [
        'env' => $_ENV[$name] ?? null,
        'server' => $_SERVER[$name] ?? null,
        'system' => getenv($name) ?: false,
    ];
}

function githubConfigUnsetEnv(): void
{
    foreach ([
        'GITHUB_API_URL',
        'GITHUB_APP_ID',
        'GITHUB_APP_PRIVATE_KEY',
        'GITHUB_WEBHOOK_SECRET',
        'GITHUB_TIMEOUT',
        'GITHUB_TOKEN_CACHE_TTL',
        'GITHUB_RETRIES',
    ] as $name) {
        unset($_ENV[$name], $_SERVER[$name]);
        putenv($name);
    }
}

function githubConfigRestoreEnv(array $saved): void
{
    foreach ($saved as $name => $state) {
        unset($_ENV[$name], $_SERVER[$name]);
        putenv($name);

        if ($state['env'] !== null) {
            $_ENV[$name] = $state['env'];
        }

        if ($state['server'] !== null) {
            $_SERVER[$name] = $state['server'];
        }

        if ($state['system'] !== false) {
            putenv("{$name}={$state['system']}");
        }
    }
}
