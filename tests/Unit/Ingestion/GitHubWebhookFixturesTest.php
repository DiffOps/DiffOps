<?php

declare(strict_types=1);

use Tests\Support\GitHubWebhookFixtures;

it('builds a realistic pull_request webhook payload', function (): void {
    $payload = GitHubWebhookFixtures::pullRequestOpened();

    expect($payload['action'])->toBe('opened')
        ->and($payload['number'])->toBeInt()
        ->and($payload['repository']['id'])->toBeInt()
        ->and($payload['installation']['id'])->toBeInt()
        ->and(strlen($payload['pull_request']['head']['sha']))->toBe(40)
        ->and(strlen($payload['pull_request']['base']['sha']))->toBe(40)
        ->and($payload['pull_request']['state'])->toBe('open')
        ->and($payload['pull_request']['user']['login'])->toBeString()
        ->and($payload['pull_request']['base']['ref'])->toBe('main');
});

it('produces a signature that matches a manual hmac hash', function (): void {
    $rawBody = json_encode(GitHubWebhookFixtures::pullRequestOpened());

    expect(GitHubWebhookFixtures::signature((string) $rawBody, 'the-secret'))
        ->toBe('sha256='.hash_hmac('sha256', (string) $rawBody, 'the-secret'));
});

it('applies payload overrides via array merge', function (): void {
    $payload = GitHubWebhookFixtures::pullRequestOpened([
        'number' => 7,
        'repository' => [
            'id' => 999,
            'full_name' => 'acme/backend',
            'owner' => ['login' => 'acme'],
            'private' => true,
        ],
    ]);

    expect($payload['number'])->toBe(7)
        ->and($payload['repository']['id'])->toBe(999)
        ->and($payload['repository']['full_name'])->toBe('acme/backend')
        ->and($payload['repository']['private'])->toBeTrue();
});

it('builds a synchronize payload with a fresh head sha', function (): void {
    $payload = GitHubWebhookFixtures::pullRequestSynchronize();

    expect($payload['action'])->toBe('synchronize')
        ->and($payload['pull_request']['head']['sha'])
        ->not->toBe(GitHubWebhookFixtures::pullRequestOpened()['pull_request']['head']['sha']);
});

it('builds closed payloads for merged and unmerged pull requests', function (): void {
    $closed = GitHubWebhookFixtures::pullRequestClosed(false);
    $merged = GitHubWebhookFixtures::pullRequestClosed(true);

    expect($closed['pull_request']['state'])->toBe('closed')
        ->and($closed['pull_request']['merged'])->toBeFalse()
        ->and($closed['pull_request']['merged_at'])->toBeNull()
        ->and($merged['pull_request']['merged'])->toBeTrue()
        ->and($merged['pull_request']['merged_at'])->not->toBeNull()
        ->and($closed['pull_request']['closed_at'])->not->toBeNull();
});

it('builds reopened, edited and ping payloads', function (): void {
    $reopened = GitHubWebhookFixtures::pullRequestReopened();
    $edited = GitHubWebhookFixtures::pullRequestEdited();
    $ping = GitHubWebhookFixtures::ping();

    expect($reopened['action'])->toBe('reopened')
        ->and($reopened['pull_request']['state'])->toBe('open')
        ->and($edited['action'])->toBe('edited')
        ->and($ping)->toHaveKey('zen');
});
