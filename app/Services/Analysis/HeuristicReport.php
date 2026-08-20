<?php

namespace App\Services\Analysis;

/**
 * Deterministic local audit of a pull request diff (H1-H4 rules).
 *
 * @immutable
 */
final class HeuristicReport
{
    /**
     * @param  list<array{category: string, severity: string, file_path: string, description: string}>  $findings
     * @param  list<string>  $sensitivePaths
     * @param  array<string, int>  $weightSums
     */
    public function __construct(
        public readonly array $findings = [],
        public readonly array $sensitivePaths = [],
        public readonly int $score = 0,
        public readonly array $weightSums = [],
    ) {}
}
