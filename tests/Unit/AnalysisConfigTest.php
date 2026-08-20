<?php

declare(strict_types=1);

it('defaults the analysis sanitizer thresholds', function (): void {
    $saved = analysisConfigSaveEnv();

    try {
        analysisConfigUnsetEnv();

        $config = require base_path('config/analysis.php');

        expect($config['sanitizer']['max_file_bytes'])->toBe(300000)
            ->and($config['sanitizer']['context_tokens'])->toBe(8192)
            ->and($config['sanitizer']['context_ratio'])->toBe(0.6)
            ->and($config['sanitizer']['excluded_paths'])->toBe(['vendor/', 'node_modules/', 'dist/', 'build/'])
            ->and($config['sanitizer']['excluded_extensions'])->toContain('png', 'woff2')
            ->and($config['sanitizer']['lockfile_keep_lines'])->toBe(20)
            ->and($config['sanitizer']['lockfile_patterns'])->toContain('composer.lock', 'package-lock.json')
            ->and($config['sanitizer']['test_asset_paths'])->toContain('/tests/', '/assets/');
    } finally {
        analysisConfigRestoreEnv($saved);
    }
});

it('defaults the analysis verdict, defcon and risk bands', function (): void {
    $saved = analysisConfigSaveEnv();

    try {
        analysisConfigUnsetEnv();

        $config = require base_path('config/analysis.php');

        expect($config['verdict']['hostile'])->toBe(70)
            ->and($config['verdict']['flagged'])->toBe(35)
            ->and($config['defcon']['bands'])->toBe([90, 70, 50, 30])
            ->and($config['risk']['high'])->toBe(70)
            ->and($config['risk']['medium'])->toBe(35);
    } finally {
        analysisConfigRestoreEnv($saved);
    }
});

it('sums the heuristic weights to a hundred cap', function (): void {
    $saved = analysisConfigSaveEnv();

    try {
        analysisConfigUnsetEnv();

        $config = require base_path('config/analysis.php');

        expect(array_sum($config['heuristic']['weights']))->toBe(100)
            ->and($config['heuristic']['max_score'])->toBe(100);
    } finally {
        analysisConfigRestoreEnv($saved);
    }
});

it('reads the analysis env overrides', function (): void {
    $saved = analysisConfigSaveEnv();

    try {
        analysisConfigUnsetEnv();

        $_ENV['ANALYSIS_MAX_FILE_BYTES'] = '123456';
        $_ENV['ANALYSIS_CONTEXT_TOKENS'] = '4096';
        $_ENV['ANALYSIS_CONTEXT_RATIO'] = '0.5';
        $_ENV['ANALYSIS_VERDICT_HOSTILE'] = '80';
        $_ENV['ANALYSIS_VERDICT_FLAGGED'] = '40';

        $config = require base_path('config/analysis.php');

        expect($config['sanitizer']['max_file_bytes'])->toBe(123456)
            ->and($config['sanitizer']['context_tokens'])->toBe(4096)
            ->and($config['sanitizer']['context_ratio'])->toBe(0.5)
            ->and($config['verdict']['hostile'])->toBe(80)
            ->and($config['verdict']['flagged'])->toBe(40);
    } finally {
        analysisConfigRestoreEnv($saved);
    }
});

it('keeps the openrouter config block free of secrets without env', function (): void {
    $saved = openrouterConfigSaveEnv();

    try {
        openrouterConfigUnsetEnv();

        $config = require base_path('config/services.php');

        expect($config['openrouter']['api_key'])->toBeNull()
            ->and($config['openrouter']['api_url'])->toBe('https://openrouter.ai/api/v1');
    } finally {
        openrouterConfigRestoreEnv($saved);
    }
});

it('defaults the openrouter endpoint, model and fallback models', function (): void {
    $saved = openrouterConfigSaveEnv();

    try {
        openrouterConfigUnsetEnv();

        $config = require base_path('config/services.php');

        expect($config['openrouter']['api_url'])->toBe('https://openrouter.ai/api/v1')
            ->and($config['openrouter']['model'])->toBe('deepseek/deepseek-chat:free')
            ->and($config['openrouter']['fallback_models'])->toBe([
                'qwen/qwen-2.5-72b-instruct:free',
                'meta-llama/llama-3.3-70b-instruct:free',
            ]);
    } finally {
        openrouterConfigRestoreEnv($saved);
    }
});

it('defaults the openrouter request parameters', function (): void {
    $saved = openrouterConfigSaveEnv();

    try {
        openrouterConfigUnsetEnv();

        $config = require base_path('config/services.php');

        expect($config['openrouter']['timeout'])->toBe(30)
            ->and($config['openrouter']['retries'])->toBe(3)
            ->and($config['openrouter']['max_tokens'])->toBe(1024)
            ->and($config['openrouter']['temperature'])->toBe(0);
    } finally {
        openrouterConfigRestoreEnv($saved);
    }
});

it('reads the openrouter env values', function (): void {
    $saved = openrouterConfigSaveEnv();

    try {
        openrouterConfigUnsetEnv();

        $_ENV['OPENROUTER_API_URL'] = 'https://openrouter.example.com/api/v1';
        $_ENV['OPENROUTER_API_KEY'] = 'sk-or-v1-test-key';
        $_ENV['OPENROUTER_MODEL'] = 'deepseek/deepseek-chat';
        $_ENV['OPENROUTER_TIMEOUT'] = '45';
        $_ENV['OPENROUTER_RETRIES'] = '5';
        $_ENV['OPENROUTER_MAX_TOKENS'] = '2048';
        $_ENV['OPENROUTER_TEMPERATURE'] = '1';

        $config = require base_path('config/services.php');

        expect($config['openrouter']['api_url'])->toBe('https://openrouter.example.com/api/v1')
            ->and($config['openrouter']['api_key'])->toBe('sk-or-v1-test-key')
            ->and($config['openrouter']['model'])->toBe('deepseek/deepseek-chat')
            ->and($config['openrouter']['timeout'])->toBe(45)
            ->and($config['openrouter']['retries'])->toBe(5)
            ->and($config['openrouter']['max_tokens'])->toBe(2048)
            ->and($config['openrouter']['temperature'])->toBe(1);
    } finally {
        openrouterConfigRestoreEnv($saved);
    }
});

it('parses comma-separated fallback models from the env', function (): void {
    $saved = openrouterConfigSaveEnv();

    try {
        openrouterConfigUnsetEnv();

        $_ENV['OPENROUTER_FALLBACK_MODELS'] = 'qwen/qwen-2.5-72b-instruct:free,meta-llama/llama-3.3-70b-instruct:free';

        $config = require base_path('config/services.php');

        expect($config['openrouter']['fallback_models'])->toBe([
            'qwen/qwen-2.5-72b-instruct:free',
            'meta-llama/llama-3.3-70b-instruct:free',
        ]);
    } finally {
        openrouterConfigRestoreEnv($saved);
    }
});

function analysisConfigSaveEnv(): array
{
    return [
        'ANALYSIS_MAX_FILE_BYTES' => analysisConfigReadEnv('ANALYSIS_MAX_FILE_BYTES'),
        'ANALYSIS_CONTEXT_TOKENS' => analysisConfigReadEnv('ANALYSIS_CONTEXT_TOKENS'),
        'ANALYSIS_CONTEXT_RATIO' => analysisConfigReadEnv('ANALYSIS_CONTEXT_RATIO'),
        'ANALYSIS_VERDICT_HOSTILE' => analysisConfigReadEnv('ANALYSIS_VERDICT_HOSTILE'),
        'ANALYSIS_VERDICT_FLAGGED' => analysisConfigReadEnv('ANALYSIS_VERDICT_FLAGGED'),
        'ANALYSIS_LOCKFILE_KEEP_LINES' => analysisConfigReadEnv('ANALYSIS_LOCKFILE_KEEP_LINES'),
    ];
}

function analysisConfigReadEnv(string $name): array
{
    return [
        'env' => $_ENV[$name] ?? null,
        'server' => $_SERVER[$name] ?? null,
        'system' => getenv($name) ?: false,
    ];
}

function analysisConfigUnsetEnv(): void
{
    foreach ([
        'ANALYSIS_MAX_FILE_BYTES',
        'ANALYSIS_CONTEXT_TOKENS',
        'ANALYSIS_CONTEXT_RATIO',
        'ANALYSIS_VERDICT_HOSTILE',
        'ANALYSIS_VERDICT_FLAGGED',
        'ANALYSIS_LOCKFILE_KEEP_LINES',
    ] as $name) {
        unset($_ENV[$name], $_SERVER[$name]);
        putenv($name);
    }
}

function analysisConfigRestoreEnv(array $saved): void
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

function openrouterConfigSaveEnv(): array
{
    return [
        'OPENROUTER_API_URL' => openrouterConfigReadEnv('OPENROUTER_API_URL'),
        'OPENROUTER_API_KEY' => openrouterConfigReadEnv('OPENROUTER_API_KEY'),
        'OPENROUTER_MODEL' => openrouterConfigReadEnv('OPENROUTER_MODEL'),
        'OPENROUTER_FALLBACK_MODELS' => openrouterConfigReadEnv('OPENROUTER_FALLBACK_MODELS'),
        'OPENROUTER_TIMEOUT' => openrouterConfigReadEnv('OPENROUTER_TIMEOUT'),
        'OPENROUTER_RETRIES' => openrouterConfigReadEnv('OPENROUTER_RETRIES'),
        'OPENROUTER_MAX_TOKENS' => openrouterConfigReadEnv('OPENROUTER_MAX_TOKENS'),
        'OPENROUTER_TEMPERATURE' => openrouterConfigReadEnv('OPENROUTER_TEMPERATURE'),
    ];
}

function openrouterConfigReadEnv(string $name): array
{
    return [
        'env' => $_ENV[$name] ?? null,
        'server' => $_SERVER[$name] ?? null,
        'system' => getenv($name) ?: false,
    ];
}

function openrouterConfigUnsetEnv(): void
{
    foreach ([
        'OPENROUTER_API_URL',
        'OPENROUTER_API_KEY',
        'OPENROUTER_MODEL',
        'OPENROUTER_FALLBACK_MODELS',
        'OPENROUTER_TIMEOUT',
        'OPENROUTER_RETRIES',
        'OPENROUTER_MAX_TOKENS',
        'OPENROUTER_TEMPERATURE',
    ] as $name) {
        unset($_ENV[$name], $_SERVER[$name]);
        putenv($name);
    }
}

function openrouterConfigRestoreEnv(array $saved): void
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
