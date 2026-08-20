<?php

namespace App\Services\Analysis;

/**
 * Single source of truth for the H1 secret patterns shared by the
 * HeuristicAuditor (detection) and the DiffSanitizer (redaction of
 * sensitive files before the diff ever reaches an LLM).
 */
final class SecretPatterns
{
    /**
     * Named regex patterns that match raw secret material inside a patch.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        return [
            'aws_access_key' => '/AKIA[0-9A-Z]{16}/',
            'github_token' => '/ghp_[A-Za-z0-9]{36}|github_pat_[A-Za-z0-9_]{20,}/',
            'private_key' => '/-----BEGIN [A-Z0-9 ]+PRIVATE KEY-----.*?-----END [A-Z0-9 ]+PRIVATE KEY-----/s',
            'openai_key' => '/sk-[A-Za-z0-9]{16,}/',
            'api_key' => '/api_key["\']?\s*[:=]\s*["\'][^"\'\s]{8,}["\']/i',
            'jwt' => '/eyJ[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}/',
        ];
    }
}
