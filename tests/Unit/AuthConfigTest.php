<?php

declare(strict_types=1);

it('keeps the supabase config block free of secrets', function (): void {
    $saved = authConfigSaveSupabaseEnv();

    try {
        authConfigUnsetSupabaseEnv();

        $config = require base_path('config/services.php');

        expect($config['supabase']['url'])->toBeNull()
            ->and($config['supabase']['jwt_secret'])->toBeNull();
    } finally {
        authConfigRestoreSupabaseEnv($saved);
    }
});

it('defaults the jwt audience and clock skew', function (): void {
    $saved = authConfigSaveSupabaseEnv();

    try {
        authConfigUnsetSupabaseEnv();

        $config = require base_path('config/services.php');

        expect($config['supabase']['jwt_audience'])->toBe('authenticated')
            ->and($config['supabase']['jwt_clock_skew'])->toBe(30);
    } finally {
        authConfigRestoreSupabaseEnv($saved);
    }
});

it('derives the jwt issuer from the supabase url', function (): void {
    $saved = authConfigSaveSupabaseEnv();

    try {
        authConfigUnsetSupabaseEnv();

        $_ENV['SUPABASE_URL'] = 'https://qkrsrfrlwclzloqjisdr.supabase.co/';

        $config = require base_path('config/services.php');

        expect($config['supabase']['jwt_issuer'])
            ->toBe('https://qkrsrfrlwclzloqjisdr.supabase.co/auth/v1');

        $_ENV['SUPABASE_JWT_ISSUER'] = 'https://custom.example.com/auth/v1';

        $config = require base_path('config/services.php');

        expect($config['supabase']['jwt_issuer'])->toBe('https://custom.example.com/auth/v1');
    } finally {
        authConfigRestoreSupabaseEnv($saved);
    }
});

function authConfigSaveSupabaseEnv(): array
{
    return [
        'url' => authConfigReadSupabaseEnv('SUPABASE_URL'),
        'secret' => authConfigReadSupabaseEnv('SUPABASE_JWT_SECRET'),
        'audience' => authConfigReadSupabaseEnv('SUPABASE_JWT_AUDIENCE'),
        'skew' => authConfigReadSupabaseEnv('SUPABASE_JWT_CLOCK_SKEW'),
        'issuer' => authConfigReadSupabaseEnv('SUPABASE_JWT_ISSUER'),
    ];
}

function authConfigReadSupabaseEnv(string $name): array
{
    return [
        'env' => $_ENV[$name] ?? null,
        'server' => $_SERVER[$name] ?? null,
        'system' => getenv($name) ?: false,
    ];
}

function authConfigUnsetSupabaseEnv(): void
{
    foreach (['SUPABASE_URL', 'SUPABASE_JWT_SECRET', 'SUPABASE_JWT_AUDIENCE', 'SUPABASE_JWT_CLOCK_SKEW', 'SUPABASE_JWT_ISSUER'] as $name) {
        unset($_ENV[$name], $_SERVER[$name]);
        putenv($name);
    }
}

function authConfigRestoreSupabaseEnv(array $saved): void
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
