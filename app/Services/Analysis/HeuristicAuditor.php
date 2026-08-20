<?php

namespace App\Services\Analysis;

/**
 * Deterministic local audit (H1-H4) of a pull request diff:
 *
 *  H1  Secret scan        — AWS keys, GitHub tokens, private keys, sk-,
 *                           api_key assignments and JWTs on ADDED lines.
 *  H2  Dependency downgrade — same package at a lower version in
 *                           composer/package manifests and lockfiles.
 *  H3  Sensitive files    — .env*, *.pem and credentials* paths.
 *  H4  Danger signals     — eval/exec/shell_exec, curl | sh, chmod 777
 *                           and subprocess shell=True on ADDED lines.
 *
 * Findings never carry the raw secret value; only a description.
 */
class HeuristicAuditor
{
    private const SECRET_LABELS = [
        'aws_access_key' => 'chave de acesso AWS',
        'github_token' => 'token de acesso GitHub',
        'private_key' => 'chave privada',
        'openai_key' => 'token de API',
        'api_key' => 'credencial api_key',
        'jwt' => 'token JWT embutido',
    ];

    private const MANIFEST_FILES = ['composer.json', 'package.json'];

    private const LOCK_FILES = ['composer.lock', 'package-lock.json'];

    private const EVAL_PATTERNS = [
        '/\beval\s*\(/',
        '/\bexec\s*\(/',
        '/shell_exec\s*\(/',
        '/shell\s*=\s*(True|true|1)/',
    ];

    private const SHELL_PATTERNS = [
        '/curl\s.*\|\s*sh\b/',
        '/chmod\s+(-R\s+)?777/',
    ];

    /**
     * @param  array<int, array<string, mixed>>  $files
     */
    public function audit(array $files): HeuristicReport
    {
        $findings = [];
        $weightSums = [];
        $sensitivePaths = [];

        foreach ($files as $file) {
            $path = (string) ($file['file_path'] ?? '');
            $patch = (string) ($file['raw_patch'] ?? '');
            $fileFindings = $this->inspectFile($path, $patch);

            $isSensitive = false;

            foreach ($fileFindings as $finding) {
                $findings[] = $finding;
                $category = $finding['category'];
                $weightSums[$category] = ($weightSums[$category] ?? 0) + $this->weight($category);

                if (in_array($category, ['secret', 'sensitive_file'], true)) {
                    $isSensitive = true;
                }
            }

            if ($isSensitive) {
                $sensitivePaths[] = $path;
            }
        }

        $score = min((int) config('analysis.heuristic.max_score', 100), array_sum($weightSums));

        return new HeuristicReport($findings, $sensitivePaths, $score, $weightSums);
    }

    /**
     * @return list<array{category: string, severity: string, file_path: string, description: string}>
     */
    private function inspectFile(string $path, string $patch): array
    {
        $findings = [];

        $this->detectSecrets($path, $patch, $findings);
        $this->detectDowngrade($path, $patch, $findings);
        $this->detectSensitiveFile($path, $findings);
        $this->detectDanger($path, $patch, $findings);

        return $findings;
    }

    /**
     * @param  list<array{category: string, severity: string, file_path: string, description: string}>  $findings
     */
    private function detectSecrets(string $path, string $patch, array &$findings): void
    {
        // The full added text is matched (not line by line) so multi-line
        // secrets such as private key blocks are detected as a whole.
        $addedText = implode("\n", $this->addedLines($patch));

        foreach (SecretPatterns::all() as $name => $pattern) {
            if (preg_match($pattern, $addedText) === 1) {
                $findings[] = [
                    'category' => 'secret',
                    'severity' => 'critical',
                    'file_path' => $path,
                    'description' => 'Possível segredo exposto no diff ('.self::SECRET_LABELS[$name].').',
                ];
            }
        }
    }

    /**
     * @param  list<array{category: string, severity: string, file_path: string, description: string}>  $findings
     */
    private function detectDowngrade(string $path, string $patch, array &$findings): void
    {
        $base = basename($path);

        if (! in_array($base, [...self::MANIFEST_FILES, ...self::LOCK_FILES], true)) {
            return;
        }

        $added = $this->addedLines($patch);
        $removed = $this->removedLines($patch);

        if (in_array($base, self::MANIFEST_FILES, true)) {
            $addedDeps = $this->manifestDependencies($added);
            $removedDeps = $this->manifestDependencies($removed);

            foreach ($addedDeps as $package => $constraint) {
                if (isset($removedDeps[$package]) && $this->isLowerVersion($constraint, $removedDeps[$package])) {
                    $findings[] = [
                        'category' => 'downgrade',
                        'severity' => 'medium',
                        'file_path' => $path,
                        'description' => "Dependência {$package} com versão reduzida no manifest.",
                    ];
                }
            }

            return;
        }

        $addedVersions = $this->lockVersions($added);
        $removedVersions = $this->lockVersions($removed);

        foreach ($addedVersions as $package => $version) {
            if (isset($removedVersions[$package]) && version_compare($version, $removedVersions[$package], '<')) {
                $findings[] = [
                    'category' => 'downgrade',
                    'severity' => 'medium',
                    'file_path' => $path,
                    'description' => "Dependência {$package} com versão reduzida no lockfile.",
                ];
            }
        }
    }

    /**
     * @param  list<array{category: string, severity: string, file_path: string, description: string}>  $findings
     */
    private function detectSensitiveFile(string $path, array &$findings): void
    {
        if ($this->isSensitivePath($path)) {
            $findings[] = [
                'category' => 'sensitive_file',
                'severity' => 'high',
                'file_path' => $path,
                'description' => 'Arquivo sensível adicionado ou alterado no diff.',
            ];
        }
    }

    /**
     * @param  list<array{category: string, severity: string, file_path: string, description: string}>  $findings
     */
    private function detectDanger(string $path, string $patch, array &$findings): void
    {
        $added = $this->addedLines($patch);

        if ($this->matchesAny($added, self::EVAL_PATTERNS)) {
            $findings[] = [
                'category' => 'eval',
                'severity' => 'high',
                'file_path' => $path,
                'description' => 'Código perigoso com execução dinâmica (eval/exec/shell_exec).',
            ];
        }

        if ($this->matchesAny($added, self::SHELL_PATTERNS)) {
            $findings[] = [
                'category' => 'shell',
                'severity' => 'high',
                'file_path' => $path,
                'description' => 'Comando shell perigoso (pipe para sh ou permissão 777).',
            ];
        }
    }

    /**
     * @return list<string>
     */
    private function addedLines(string $patch): array
    {
        return array_values(array_filter(
            explode("\n", $patch),
            static fn (string $line): bool => str_starts_with($line, '+') && ! str_starts_with($line, '+++'),
        ));
    }

    /**
     * @return list<string>
     */
    private function removedLines(string $patch): array
    {
        return array_values(array_filter(
            explode("\n", $patch),
            static fn (string $line): bool => str_starts_with($line, '-') && ! str_starts_with($line, '---'),
        ));
    }

    /**
     * @param  list<string>  $lines
     * @return array<string, string>
     */
    private function manifestDependencies(array $lines): array
    {
        $dependencies = [];

        foreach ($lines as $line) {
            if (preg_match('/"([A-Za-z0-9_\-\/]+)"\s*:\s*"([^"]+)"/', $line, $matches) === 1) {
                $dependencies[$matches[1]] = $matches[2];
            }
        }

        return $dependencies;
    }

    /**
     * @param  list<string>  $lines
     * @return array<string, string>
     */
    private function lockVersions(array $lines): array
    {
        $versions = [];
        $current = null;

        foreach ($lines as $line) {
            if (preg_match('/"name"\s*:\s*"([^"]+)"/', $line, $matches) === 1) {
                $current = $matches[1];
            }

            if ($current !== null && preg_match('/"version"\s*:\s*"([^"]+)"/', $line, $matches) === 1) {
                $versions[$current] = $matches[1];
                $current = null;
            }
        }

        return $versions;
    }

    private function isLowerVersion(string $newConstraint, string $oldConstraint): bool
    {
        $newVersion = $this->firstVersion($newConstraint);
        $oldVersion = $this->firstVersion($oldConstraint);

        return $newVersion !== null
            && $oldVersion !== null
            && version_compare($newVersion, $oldVersion, '<');
    }

    private function firstVersion(string $constraint): ?string
    {
        if (preg_match('/\d+(?:\.\d+)*(?:-[0-9A-Za-z.-]+)?/', $constraint, $matches) !== 1) {
            return null;
        }

        return $matches[0];
    }

    private function isSensitivePath(string $path): bool
    {
        $base = basename($path);

        return preg_match('/^\.env(\..*)?$/', $base) === 1
            || str_ends_with(strtolower($base), '.pem')
            || str_starts_with(strtolower($base), 'credentials');
    }

    /**
     * @param  list<string>  $lines
     * @param  list<string>  $patterns
     */
    private function matchesAny(array $lines, array $patterns): bool
    {
        foreach ($lines as $line) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $line) === 1) {
                    return true;
                }
            }
        }

        return false;
    }

    private function weight(string $category): int
    {
        $key = match ($category) {
            'sensitive_file' => 'sensitive',
            default => $category,
        };

        return (int) config("analysis.heuristic.weights.{$key}", 0);
    }
}
