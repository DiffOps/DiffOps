<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Static fixtures of OpenRouter chat completion responses.
 *
 * Deliberately a static class (no global functions) so Pest never hits
 * "Cannot redeclare" across test files sharing the same process.
 */
final class OpenRouterFixtures
{
    public const USAGE = [
        'prompt_tokens' => 120,
        'completion_tokens' => 80,
        'total_tokens' => 200,
    ];

    public static function completion(array $parsed, array $usage = []): array
    {
        return self::completionWithRawContent((string) json_encode($parsed), $usage);
    }

    public static function completionWithRawContent(string $content, array $usage = []): array
    {
        return [
            'choices' => [['message' => ['content' => $content]]],
            'usage' => $usage ?: self::USAGE,
        ];
    }

    public static function hostileJson(): array
    {
        return [
            'verdict' => 'hostile',
            'threat_score' => 90,
            'defcon_level' => 1,
            'flags' => ['secret-exposed'],
            'findings' => [
                ['category' => 'secret', 'severity' => 'critical', 'file_path' => '.env', 'description' => 'credencial exposta'],
            ],
        ];
    }

    public static function flaggedJson(): array
    {
        return [
            'verdict' => 'flagged',
            'threat_score' => 50,
            'defcon_level' => 3,
            'flags' => ['eval-in-added-line'],
            'findings' => [
                ['category' => 'eval', 'severity' => 'high', 'file_path' => 'app/Helper.php', 'description' => 'eval em código novo'],
            ],
        ];
    }

    public static function clearJson(): array
    {
        return [
            'verdict' => 'clear',
            'threat_score' => 5,
            'defcon_level' => 5,
            'flags' => [],
            'findings' => [],
        ];
    }

    public static function fencesWrapped(): string
    {
        return "```json\n".json_encode(self::clearJson())."\n```";
    }

    public static function markdownPreamble(): string
    {
        return "Análise concluída:\n".json_encode(self::flaggedJson());
    }

    public static function brokenJson(): string
    {
        return '{"verdict":"clear","threat_score":10';
    }

    public static function nonJsonText(): string
    {
        return 'o modelo alucinou e respondeu texto solto';
    }

    public static function rateLimited429(): array
    {
        return ['error' => ['message' => 'Rate limit exceeded.']];
    }

    public static function serverError500(): array
    {
        return ['error' => ['message' => 'Internal server error.']];
    }

    public static function overloaded503(): array
    {
        return ['error' => ['message' => 'Provider overloaded.']];
    }
}
