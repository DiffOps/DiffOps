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

it('keeps the jwks url out of the config without a supabase url', function (): void {
    $saved = jwksConfigSaveSupabaseEnv();

    try {
        jwksConfigUnsetSupabaseEnv();

        $config = require base_path('config/services.php');

        expect($config['supabase']['jwks_url'])->toBeNull();
    } finally {
        jwksConfigRestoreSupabaseEnv($saved);
    }
});

it('derives the jwks url from the supabase url with or without a trailing slash', function (string $url, string $expected): void {
    $saved = jwksConfigSaveSupabaseEnv();

    try {
        jwksConfigUnsetSupabaseEnv();

        $_ENV['SUPABASE_URL'] = $url;

        $config = require base_path('config/services.php');

        expect($config['supabase']['jwks_url'])->toBe($expected);
    } finally {
        jwksConfigRestoreSupabaseEnv($saved);
    }
})->with([
    'with trailing slash' => ['https://qkrsrfrlwclzloqjisdr.supabase.co/', 'https://qkrsrfrlwclzloqjisdr.supabase.co/auth/v1/.well-known/jwks.json'],
    'without trailing slash' => ['https://qkrsrfrlwclzloqjisdr.supabase.co', 'https://qkrsrfrlwclzloqjisdr.supabase.co/auth/v1/.well-known/jwks.json'],
]);

it('defaults the jwks cache ttl, timeout and last login debounce', function (): void {
    $saved = jwksConfigSaveSupabaseEnv();

    try {
        jwksConfigUnsetSupabaseEnv();

        $config = require base_path('config/services.php');

        expect($config['supabase']['jwks_cache_ttl'])->toBe(3600)
            ->and($config['supabase']['jwks_timeout'])->toBe(5)
            ->and($config['supabase']['last_login_debounce'])->toBe(300);
    } finally {
        jwksConfigRestoreSupabaseEnv($saved);
    }
});

it('overrides the jwks url via env', function (): void {
    $saved = jwksConfigSaveSupabaseEnv();

    try {
        jwksConfigUnsetSupabaseEnv();

        $_ENV['SUPABASE_JWKS_URL'] = 'https://custom.example.com/jwks.json';

        $config = require base_path('config/services.php');

        expect($config['supabase']['jwks_url'])
            ->toBe('https://custom.example.com/jwks.json');
    } finally {
        jwksConfigRestoreSupabaseEnv($saved);
    }
});

it('defaults the profile sync http flag, timeout and cache ttl', function (): void {
    $saved = profileSyncConfigSaveSupabaseEnv();

    try {
        profileSyncConfigUnsetSupabaseEnv();

        $config = require base_path('config/services.php');

        expect($config['supabase']['profile_sync_http'])->toBeFalse()
            ->and($config['supabase']['profile_sync_timeout'])->toBe(5)
            ->and($config['supabase']['profile_sync_cache_ttl'])->toBe(300);
    } finally {
        profileSyncConfigRestoreSupabaseEnv($saved);
    }
});

it('parses the profile sync http env flag as a boolean', function (string $raw, bool $expected): void {
    $saved = profileSyncConfigSaveSupabaseEnv();

    try {
        profileSyncConfigUnsetSupabaseEnv();

        $_ENV['SUPABASE_PROFILE_SYNC_HTTP'] = $raw;

        $config = require base_path('config/services.php');

        expect($config['supabase']['profile_sync_http'])->toBe($expected);
    } finally {
        profileSyncConfigRestoreSupabaseEnv($saved);
    }
})->with([
    'true' => ['true', true],
    'false' => ['false', false],
]);

it('keeps the profile sync url out of the config without a supabase url', function (): void {
    $saved = profileSyncConfigSaveSupabaseEnv();

    try {
        profileSyncConfigUnsetSupabaseEnv();

        $config = require base_path('config/services.php');

        expect($config['supabase']['profile_sync_url'])->toBeNull();
    } finally {
        profileSyncConfigRestoreSupabaseEnv($saved);
    }
});

it('derives the profile sync url from the supabase url with or without a trailing slash', function (string $url, string $expected): void {
    $saved = profileSyncConfigSaveSupabaseEnv();

    try {
        profileSyncConfigUnsetSupabaseEnv();

        $_ENV['SUPABASE_URL'] = $url;

        $config = require base_path('config/services.php');

        expect($config['supabase']['profile_sync_url'])->toBe($expected);
    } finally {
        profileSyncConfigRestoreSupabaseEnv($saved);
    }
})->with([
    'with trailing slash' => ['https://qkrsrfrlwclzloqjisdr.supabase.co/', 'https://qkrsrfrlwclzloqjisdr.supabase.co/auth/v1/user'],
    'without trailing slash' => ['https://qkrsrfrlwclzloqjisdr.supabase.co', 'https://qkrsrfrlwclzloqjisdr.supabase.co/auth/v1/user'],
]);

it('overrides the profile sync url via env', function (): void {
    $saved = profileSyncConfigSaveSupabaseEnv();

    try {
        profileSyncConfigUnsetSupabaseEnv();

        $_ENV['SUPABASE_PROFILE_SYNC_URL'] = 'https://custom.example.com/user';

        $config = require base_path('config/services.php');

        expect($config['supabase']['profile_sync_url'])
            ->toBe('https://custom.example.com/user');
    } finally {
        profileSyncConfigRestoreSupabaseEnv($saved);
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

function jwksConfigSaveSupabaseEnv(): array
{
    return [
        'url' => jwksConfigReadSupabaseEnv('SUPABASE_URL'),
        'jwks_url' => jwksConfigReadSupabaseEnv('SUPABASE_JWKS_URL'),
        'ttl' => jwksConfigReadSupabaseEnv('SUPABASE_JWKS_CACHE_TTL'),
        'timeout' => jwksConfigReadSupabaseEnv('SUPABASE_JWKS_TIMEOUT'),
        'debounce' => jwksConfigReadSupabaseEnv('SUPABASE_LAST_LOGIN_DEBOUNCE'),
    ];
}

function jwksConfigReadSupabaseEnv(string $name): array
{
    return [
        'env' => $_ENV[$name] ?? null,
        'server' => $_SERVER[$name] ?? null,
        'system' => getenv($name) ?: false,
    ];
}

function jwksConfigUnsetSupabaseEnv(): void
{
    foreach (['SUPABASE_URL', 'SUPABASE_JWKS_URL', 'SUPABASE_JWKS_CACHE_TTL', 'SUPABASE_JWKS_TIMEOUT', 'SUPABASE_LAST_LOGIN_DEBOUNCE'] as $name) {
        unset($_ENV[$name], $_SERVER[$name]);
        putenv($name);
    }
}

function jwksConfigRestoreSupabaseEnv(array $saved): void
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

function profileSyncConfigSaveSupabaseEnv(): array
{
    return [
        'url' => profileSyncConfigReadSupabaseEnv('SUPABASE_URL'),
        'sync_url' => profileSyncConfigReadSupabaseEnv('SUPABASE_PROFILE_SYNC_URL'),
        'http' => profileSyncConfigReadSupabaseEnv('SUPABASE_PROFILE_SYNC_HTTP'),
        'timeout' => profileSyncConfigReadSupabaseEnv('SUPABASE_PROFILE_SYNC_TIMEOUT'),
        'ttl' => profileSyncConfigReadSupabaseEnv('SUPABASE_PROFILE_SYNC_CACHE_TTL'),
    ];
}

function profileSyncConfigReadSupabaseEnv(string $name): array
{
    return [
        'env' => $_ENV[$name] ?? null,
        'server' => $_SERVER[$name] ?? null,
        'system' => getenv($name) ?: false,
    ];
}

function profileSyncConfigUnsetSupabaseEnv(): void
{
    foreach (['SUPABASE_URL', 'SUPABASE_PROFILE_SYNC_URL', 'SUPABASE_PROFILE_SYNC_HTTP', 'SUPABASE_PROFILE_SYNC_TIMEOUT', 'SUPABASE_PROFILE_SYNC_CACHE_TTL'] as $name) {
        unset($_ENV[$name], $_SERVER[$name]);
        putenv($name);
    }
}

function profileSyncConfigRestoreSupabaseEnv(array $saved): void
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
