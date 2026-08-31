<?php

declare(strict_types=1);

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\Support\GitHubWebhookFixtures;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('services.github.webhook_secret', 'the-webhook-secret');
});

function auditGitHubWebhookCall(string $event, array $payload, ?string $secret = 'the-webhook-secret'): TestResponse
{
    $rawBody = json_encode($payload);
    $server = [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_GITHUB_EVENT' => $event,
        'HTTP_X_GITHUB_DELIVERY' => GitHubWebhookFixtures::DELIVERY_ID,
    ];

    if ($secret !== null) {
        $server['HTTP_X_HUB_SIGNATURE_256'] = GitHubWebhookFixtures::signature((string) $rawBody, $secret);
    }

    return test()->call('POST', '/api/webhooks/github', [], [], [], $server, $rawBody);
}

it('logs webhook.received when a valid signed pull_request event arrives', function (): void {
    Queue::fake();

    auditGitHubWebhookCall('pull_request', GitHubWebhookFixtures::pullRequestOpened())
        ->assertOk();

    $log = AuditLog::where('action', 'webhook.received')->first();
    expect($log)->not->toBeNull()
        ->and($log->entity_type)->toBe('pull_request')
        ->and($log->user_id)->toBeNull()
        ->and($log->payload['event'])->toBe('pull_request')
        ->and($log->payload['action'])->toBe('opened')
        ->and($log->payload['delivery_id'])->toBe(GitHubWebhookFixtures::DELIVERY_ID)
        ->and($log->payload['repo_full_name'])->toBe('acme/web')
        ->and($log->payload['pr_number'])->toBe(42);
});

it('does not leak any raw secret values in the audit payload', function (): void {
    Queue::fake();

    auditGitHubWebhookCall('pull_request', GitHubWebhookFixtures::pullRequestOpened())
        ->assertOk();

    $log = AuditLog::where('action', 'webhook.received')->first();
    expect($log)->not->toBeNull();
    $raw = json_encode($log->payload);
    // The sanitiser masks token-shaped values; the webhook payload itself
    // contains no real secrets, so we mainly assert that no secret marker
    // leaks in.
    expect($raw)->not->toContain('ghp_')
        ->and($raw)->not->toContain('[REDACTED]');
});

it('logs webhook.ping for a ping event', function (): void {
    Queue::fake();

    auditGitHubWebhookCall('ping', GitHubWebhookFixtures::ping())
        ->assertOk();

    $log = AuditLog::where('action', 'webhook.ping')->first();
    expect($log)->not->toBeNull()
        ->and($log->payload['event'])->toBe('ping')
        ->and($log->payload['delivery_id'])->toBe(GitHubWebhookFixtures::DELIVERY_ID);
});
