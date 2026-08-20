<?php

namespace App\Services\Analysis;

/**
 * Sanitizes GitHub Files API records into AI-safe context chunks:
 * excluded paths and binary assets are dropped, lockfiles are truncated,
 * files are capped by byte size, secrets of sensitive files are redacted
 * and the surviving patches are grouped by priority into chunks that fit
 * the configured context budget.
 */
class DiffSanitizer
{
    /**
     * @param  array<int, array<string, mixed>>  $files
     * @return list<Chunk>
     */
    public function sanitize(array $files, HeuristicReport $report): array
    {
        $sanitized = [];

        foreach ($files as $file) {
            $path = (string) ($file['file_path'] ?? '');

            if ($this->isExcluded($path, $file)) {
                continue;
            }

            $patch = (string) ($file['raw_patch'] ?? '');

            if (in_array($path, $report->sensitivePaths, true)) {
                $patch = $this->redact($patch);
            }

            if ($this->isLockfile($path)) {
                $patch = $this->truncateLockfile($patch);
            }

            $patch = $this->applyByteCap($patch);

            if ($patch === '') {
                continue;
            }

            $sanitized[] = [
                'file_path' => $path,
                'raw_patch' => $patch,
                'estimated_tokens' => max(1, (int) ceil(strlen($patch) / 4)),
            ];
        }

        usort(
            $sanitized,
            static fn (array $a, array $b): int => [
                self::priority($a['file_path']),
                $a['file_path'],
            ] <=> [
                self::priority($b['file_path']),
                $b['file_path'],
            ],
        );

        return $this->chunk($sanitized);
    }

    /**
     * @param  array<int, array{file_path: string, raw_patch: string, estimated_tokens: int}>  $files
     * @return list<Chunk>
     */
    private function chunk(array $files): array
    {
        if ($files === []) {
            return [];
        }

        $budget = (int) ((float) config('analysis.sanitizer.context_tokens', 8192)
            * (float) config('analysis.sanitizer.context_ratio', 0.6));

        $chunks = [];
        $current = [];
        $currentTokens = 0;

        foreach ($files as $file) {
            $tokens = $file['estimated_tokens'];

            if ($current !== [] && $currentTokens + $tokens > $budget) {
                $chunks[] = new Chunk($current, $currentTokens, count($chunks));
                $current = [];
                $currentTokens = 0;
            }

            $current[] = $file;
            $currentTokens += $tokens;
        }

        if ($current !== []) {
            $chunks[] = new Chunk($current, $currentTokens, count($chunks));
        }

        return $chunks;
    }

    /**
     * @param  array<string, mixed>  $file
     */
    private function isExcluded(string $path, array $file): bool
    {
        if (($file['is_binary'] ?? false) === true) {
            return true;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($extension, config('analysis.sanitizer.excluded_extensions', []), true)) {
            return true;
        }

        $normalized = '/'.ltrim($path, '/');

        foreach (config('analysis.sanitizer.excluded_paths', []) as $needle) {
            if (str_contains($normalized, '/'.trim((string) $needle, '/'))) {
                return true;
            }
        }

        return false;
    }

    private function isLockfile(string $path): bool
    {
        return in_array(basename($path), config('analysis.sanitizer.lockfile_patterns', []), true);
    }

    private function truncateLockfile(string $patch): string
    {
        $keep = (int) config('analysis.sanitizer.lockfile_keep_lines', 20);
        $lines = explode("\n", $patch);

        $header = array_slice($lines, 0, $keep);
        $rest = array_slice($lines, $keep);

        $versionLines = array_values(array_filter(
            $rest,
            static fn (string $line): bool => preg_match('/"(version|resolved)"\s*:/i', $line) === 1
                || preg_match('/^\s*version\s+/i', $line) === 1,
        ));

        $body = array_slice($versionLines, 0, $keep);

        return implode("\n", [...$header, ...$body, '// [DiffOps] lockfile truncado']);
    }

    private function applyByteCap(string $patch): string
    {
        $max = (int) config('analysis.sanitizer.max_file_bytes', 300000);

        if (strlen($patch) <= $max) {
            return $patch;
        }

        return substr($patch, 0, $max)."\n// [DiffOps] arquivo truncado por tamanho";
    }

    private function redact(string $patch): string
    {
        foreach (SecretPatterns::all() as $pattern) {
            $patch = (string) preg_replace($pattern, '[REDACTED]', $patch);
        }

        return $patch;
    }

    private static function priority(string $path): int
    {
        $normalized = '/'.ltrim($path, '/');

        foreach (config('analysis.sanitizer.test_asset_paths', []) as $needle) {
            if (str_contains($normalized, (string) $needle)) {
                return 1;
            }
        }

        return 0;
    }
}
