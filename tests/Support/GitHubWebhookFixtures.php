<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Static fixtures for GitHub webhook payloads (pull_request events).
 *
 * Deliberately a static class (no global functions) so Pest never hits
 * "Cannot redeclare" across test files sharing the same process.
 */
final class GitHubWebhookFixtures
{
    public const EVENT = 'pull_request';

    public const DELIVERY_ID = '123e4567-e89b-12d3-a456-426614174000';

    public static function pullRequestOpened(array $overrides = []): array
    {
        return self::basePullRequest($overrides);
    }

    public static function pullRequestSynchronize(array $overrides = []): array
    {
        return self::basePullRequest(array_merge([
            'action' => 'synchronize',
            'pull_request' => [
                'state' => 'open',
                'title' => 'Add rate limiting to the api',
                'user' => ['login' => 'devacme', 'avatar_url' => 'https://avatars.example.com/devacme.png'],
                'base' => ['ref' => 'main', 'sha' => str_repeat('b', 40)],
                'head' => ['ref' => 'feat/rate-limit', 'sha' => str_repeat('c', 40)],
                'draft' => false,
                'merged' => false,
                'closed_at' => null,
                'merged_at' => null,
            ],
        ], $overrides));
    }

    public static function pullRequestClosed(bool $merged, array $overrides = []): array
    {
        $now = now()->toIso8601String();

        return self::basePullRequest(array_merge([
            'action' => 'closed',
            'pull_request' => [
                'state' => 'closed',
                'title' => 'Add rate limiting to the api',
                'user' => ['login' => 'devacme', 'avatar_url' => 'https://avatars.example.com/devacme.png'],
                'base' => ['ref' => 'main', 'sha' => str_repeat('b', 40)],
                'head' => ['ref' => 'feat/rate-limit', 'sha' => str_repeat('a', 40)],
                'draft' => false,
                'merged' => $merged,
                'closed_at' => $now,
                'merged_at' => $merged ? $now : null,
            ],
        ], $overrides));
    }

    public static function pullRequestReopened(array $overrides = []): array
    {
        return self::basePullRequest(array_merge([
            'action' => 'reopened',
            'pull_request' => [
                'state' => 'open',
                'title' => 'Add rate limiting to the api',
                'user' => ['login' => 'devacme', 'avatar_url' => 'https://avatars.example.com/devacme.png'],
                'base' => ['ref' => 'main', 'sha' => str_repeat('b', 40)],
                'head' => ['ref' => 'feat/rate-limit', 'sha' => str_repeat('a', 40)],
                'draft' => false,
                'merged' => false,
                'closed_at' => null,
                'merged_at' => null,
            ],
        ], $overrides));
    }

    public static function pullRequestEdited(array $overrides = []): array
    {
        return self::basePullRequest(array_merge([
            'action' => 'edited',
            'pull_request' => [
                'state' => 'open',
                'title' => 'Add rate limiting to the api (edited)',
                'user' => ['login' => 'devacme', 'avatar_url' => 'https://avatars.example.com/devacme.png'],
                'base' => ['ref' => 'main', 'sha' => str_repeat('b', 40)],
                'head' => ['ref' => 'feat/rate-limit', 'sha' => str_repeat('a', 40)],
                'draft' => false,
                'merged' => false,
                'closed_at' => null,
                'merged_at' => null,
            ],
        ], $overrides));
    }

    public static function ping(array $overrides = []): array
    {
        return array_merge([
            'zen' => 'Keep it logically awesome.',
            'hook_id' => 123456,
        ], $overrides);
    }

    public static function signature(string $rawBody, string $secret): string
    {
        return 'sha256='.hash_hmac('sha256', $rawBody, $secret);
    }

    private static function basePullRequest(array $overrides = []): array
    {
        return array_merge([
            'action' => 'opened',
            'number' => 42,
            'pull_request' => [
                'state' => 'open',
                'title' => 'Add rate limiting to the api',
                'user' => ['login' => 'devacme', 'avatar_url' => 'https://avatars.example.com/devacme.png'],
                'base' => ['ref' => 'main', 'sha' => str_repeat('b', 40)],
                'head' => ['ref' => 'feat/rate-limit', 'sha' => str_repeat('a', 40)],
                'draft' => false,
                'merged' => false,
                'closed_at' => null,
                'merged_at' => null,
            ],
            'repository' => [
                'id' => 424242,
                'full_name' => 'acme/web',
                'owner' => ['login' => 'acme'],
                'private' => false,
            ],
            'sender' => ['login' => 'devacme'],
            'installation' => ['id' => 987654],
            'organization' => ['login' => 'acme'],
        ], $overrides);
    }
}
