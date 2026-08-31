<?php

declare(strict_types=1);

use App\Jobs\AnalyzeIncursionJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\Support\GitHubWebhookFixtures;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('services.github.webhook_secret', 'the-webhook-secret');
});

function githubWebhookCall(string $event, array $payload, ?string $secret = 'the-webhook-secret'): TestResponse
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

it('accepts a signed pull_request opened event and dispatches the job', function (): void {
    Queue::fake();

    githubWebhookCall('pull_request', GitHubWebhookFixtures::pullRequestOpened())
        ->assertOk();

    Queue::assertPushed(ProcessIncursionJob::class, fn (ProcessIncursionJob $job): bool => $job->deliveryId === GitHubWebhookFixtures::DELIVERY_ID);
});

it('dispatches the job for every supported pull_request action', function (string $action, array $payload): void {
    Queue::fake();

    githubWebhookCall('pull_request', $payload)->assertOk();

    Queue::assertPushed(ProcessIncursionJob::class, fn (ProcessIncursionJob $job): bool => $job->deliveryId === GitHubWebhookFixtures::DELIVERY_ID);
})->with([
    'synchronize' => ['synchronize', GitHubWebhookFixtures::pullRequestSynchronize()],
    'reopened' => ['reopened', GitHubWebhookFixtures::pullRequestReopened()],
    'closed' => ['closed', GitHubWebhookFixtures::pullRequestClosed(false)],
    'edited' => ['edited', GitHubWebhookFixtures::pullRequestEdited()],
]);

it('answers 200 to a ping event without dispatching a job', function (): void {
    Queue::fake();

    githubWebhookCall('ping', GitHubWebhookFixtures::ping())
        ->assertOk();

    Queue::assertNothingPushed();
});

it('ignores webhook events that are not pull_request', function (): void {
    Queue::fake();

    githubWebhookCall('issues', [
        'action' => 'opened',
        'issue' => ['number' => 1],
    ])->assertOk();

    Queue::assertNothingPushed();
});

it('ignores unsupported pull_request actions', function (): void {
    Queue::fake();

    githubWebhookCall('pull_request', GitHubWebhookFixtures::pullRequestOpened(['action' => 'labeled']))
        ->assertOk();

    Queue::assertNothingPushed();
});

it('dispatches the analysis job for diffing actions', function (string $action, array $payload): void {
    Queue::fake();

    githubWebhookCall('pull_request', $payload)->assertOk();

    Queue::assertPushed(AnalyzeIncursionJob::class, fn (AnalyzeIncursionJob $job): bool => $job->deliveryId === GitHubWebhookFixtures::DELIVERY_ID);
})->with([
    'opened' => ['opened', GitHubWebhookFixtures::pullRequestOpened()],
    'synchronize' => ['synchronize', GitHubWebhookFixtures::pullRequestSynchronize()],
    'reopened' => ['reopened', GitHubWebhookFixtures::pullRequestReopened()],
]);

it('does not dispatch the analysis job for non-diffing events', function (string $event, array $payload): void {
    Queue::fake();

    githubWebhookCall($event, $payload)->assertOk();

    Queue::assertNotPushed(AnalyzeIncursionJob::class);
})->with([
    'closed' => ['pull_request', GitHubWebhookFixtures::pullRequestClosed(false)],
    'edited' => ['pull_request', GitHubWebhookFixtures::pullRequestEdited()],
    'labeled' => ['pull_request', GitHubWebhookFixtures::pullRequestOpened(['action' => 'labeled'])],
    'ping' => ['ping', GitHubWebhookFixtures::ping()],
    'issues' => ['issues', ['action' => 'opened', 'issue' => ['number' => 1]]],
]);

it('rejects unsigned webhook requests through the real route', function (): void {
    Queue::fake();

    githubWebhookCall('pull_request', GitHubWebhookFixtures::pullRequestOpened(), secret: null)
        ->assertStatus(401)
        ->assertExactJson(['message' => 'Invalid signature.']);

    Queue::assertNothingPushed();
});

it('rejects webhook requests signed with the wrong secret', function (): void {
    Queue::fake();

    githubWebhookCall('pull_request', GitHubWebhookFixtures::pullRequestOpened(), secret: 'wrong-secret')
        ->assertStatus(401);

    Queue::assertNothingPushed();
});
