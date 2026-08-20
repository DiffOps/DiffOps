<?php

declare(strict_types=1);

use App\Services\Analysis\DiffSanitizer;
use App\Services\Analysis\HeuristicReport;
use Tests\Support\DiffFixtures;

function sanitizerFile(array $overrides = []): array
{
    return array_merge([
        'file_path' => 'app/Example.php',
        'raw_patch' => "@@ -1,3 +1,4 @@\n+echo 'hi';\n",
        'is_binary' => false,
        'status' => 'modified',
    ], $overrides);
}

function sanitizeFiles(array $files, array $sensitivePaths = []): array
{
    return app(DiffSanitizer::class)->sanitize($files, new HeuristicReport(sensitivePaths: $sensitivePaths));
}

it('drops files under excluded paths', function (): void {
    $chunks = sanitizeFiles([DiffFixtures::vendorFile(), DiffFixtures::benignPhpDiff()]);

    expect($chunks)->toHaveCount(1)
        ->and($chunks[0]->files)->toHaveCount(1)
        ->and($chunks[0]->files[0]['file_path'])->toBe('app/Http/Controllers/HomeController.php');
});

it('drops binaries and files with excluded extensions', function (): void {
    $chunks = sanitizeFiles([
        DiffFixtures::binaryRecord(),
        sanitizerFile(['file_path' => 'assets/logo.png', 'raw_patch' => 'not binary but image']),
        DiffFixtures::benignPhpDiff(),
    ]);

    expect($chunks)->toHaveCount(1)
        ->and($chunks[0]->files)->toHaveCount(1)
        ->and($chunks[0]->files[0]['file_path'])->toBe('app/Http/Controllers/HomeController.php');
});

it('truncates lockfiles keeping the header and version lines', function (): void {
    $chunks = sanitizeFiles([DiffFixtures::hugeLockfile(500)]);

    $patch = $chunks[0]->files[0]['raw_patch'];

    expect($patch)->toContain('// [DiffOps] lockfile truncado')
        ->toContain('"packages": [')
        ->toContain('"version": "1.9.0",')
        ->not->toContain('acme/package-499')
        ->and(substr_count($patch, "\n"))->toBeLessThan(100);
});

it('caps a single file to the max file bytes', function (): void {
    $chunks = sanitizeFiles([sanitizerFile(['raw_patch' => str_repeat('a', 400000)])]);

    $patch = $chunks[0]->files[0]['raw_patch'];

    expect(strlen($patch))->toBe(300042)
        ->and($patch)->toContain('// [DiffOps] arquivo truncado por tamanho');
});

it('estimates tokens as ceil of chars over four', function (): void {
    $chunks = sanitizeFiles([sanitizerFile(['raw_patch' => str_repeat('a', 100)])]);

    expect($chunks[0]->files[0]['estimated_tokens'])->toBe(25)
        ->and($chunks[0]->estimated_tokens)->toBe(25);
});

it('respects the context ratio budget inside every chunk', function (): void {
    $chunks = sanitizeFiles([
        sanitizerFile(['file_path' => 'app/A.php', 'raw_patch' => str_repeat('a', 8000)]),
        sanitizerFile(['file_path' => 'app/B.php', 'raw_patch' => str_repeat('a', 8000)]),
        sanitizerFile(['file_path' => 'app/C.php', 'raw_patch' => str_repeat('a', 8000)]),
    ]);

    $budget = (int) (8192 * 0.6);

    expect($chunks)->toHaveCount(2);

    foreach ($chunks as $chunk) {
        expect($chunk->estimated_tokens)->toBeLessThanOrEqual($budget);
    }

    expect($chunks[0]->estimated_tokens)->toBe(4000)
        ->and($chunks[1]->estimated_tokens)->toBe(2000);
});

it('orders files by priority core before test or asset', function (): void {
    $chunks = sanitizeFiles([
        sanitizerFile(['file_path' => 'tests/Feature/AnalysisTest.php']),
        sanitizerFile(['file_path' => 'app/Http/Controllers/HomeController.php']),
        sanitizerFile(['file_path' => 'public/docs/readme.md']),
    ]);

    expect($chunks[0]->files)->toHaveCount(3)
        ->and(array_column($chunks[0]->files, 'file_path'))->toBe([
            'app/Http/Controllers/HomeController.php',
            'public/docs/readme.md',
            'tests/Feature/AnalysisTest.php',
        ]);
});

it('splits into multiple chunks with sequential indexes', function (): void {
    $chunks = sanitizeFiles([
        sanitizerFile(['file_path' => 'app/A.php', 'raw_patch' => str_repeat('a', 8000)]),
        sanitizerFile(['file_path' => 'app/B.php', 'raw_patch' => str_repeat('a', 8000)]),
        sanitizerFile(['file_path' => 'app/C.php', 'raw_patch' => str_repeat('a', 8000)]),
    ]);

    expect($chunks)->toHaveCount(2)
        ->and($chunks[0]->index)->toBe(0)
        ->and($chunks[1]->index)->toBe(1)
        ->and($chunks[0]->files)->toHaveCount(2)
        ->and($chunks[1]->files)->toHaveCount(1);
});

it('returns an empty chunk list for an empty input', function (): void {
    expect(sanitizeFiles([]))->toBe([]);
});

it('redacts secret values from files flagged as sensitive', function (): void {
    $chunks = sanitizeFiles([DiffFixtures::exposedDotEnvSecret()], ['.env']);

    $patch = $chunks[0]->files[0]['raw_patch'];

    expect($patch)->toContain('[REDACTED]')
        ->not->toContain('sk-live-1234567890abcdef');
});

it('keeps the sanitized output deterministic', function (): void {
    $files = [
        DiffFixtures::exposedDotEnvSecret(),
        DiffFixtures::benignPhpDiff(),
        DiffFixtures::hugeLockfile(50),
    ];

    $first = serialize(sanitizeFiles($files, ['.env']));
    $second = serialize(sanitizeFiles($files, ['.env']));

    expect($first)->toBe($second);
});

it('does not redact files that were not flagged as sensitive', function (): void {
    $chunks = sanitizeFiles([DiffFixtures::awsKeyInConfig()], []);

    $patch = $chunks[0]->files[0]['raw_patch'];

    expect($patch)->toContain('AKIAIOSFODNN7EXAMPLE')
        ->not->toContain('[REDACTED]');
});

it('carries the original file path and patch into every chunk', function (): void {
    $chunks = sanitizeFiles([DiffFixtures::benignPhpDiff()]);

    expect($chunks[0]->files[0])->toMatchArray([
        'file_path' => 'app/Http/Controllers/HomeController.php',
        'raw_patch' => "@@ -1,3 +1,4 @@\n+public function index()\n+{\n+    return view('home');\n+}\n",
    ])->and($chunks[0]->files[0])->toHaveKey('estimated_tokens');
});
