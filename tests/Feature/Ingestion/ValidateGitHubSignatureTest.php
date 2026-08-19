<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\Support\GitHubWebhookFixtures;

beforeEach(function (): void {
    config()->set('services.github.webhook_secret', 'the-webhook-secret');

    Route::middleware('validate.github.signature')->post('/_github/probe', fn () => response()->json(['ok' => true]));
});

function githubProbeRequest(string $rawBody, string $event = 'ping', ?string $signature = null): TestResponse
{
    $server = [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_GITHUB_EVENT' => $event,
        'HTTP_X_GITHUB_DELIVERY' => GitHubWebhookFixtures::DELIVERY_ID,
    ];

    if ($signature !== null) {
        $server['HTTP_X_HUB_SIGNATURE_256'] = $signature;
    }

    return test()->call('POST', '/_github/probe', [], [], [], $server, $rawBody);
}

it('accepts a request signed with the webhook secret', function (): void {
    $rawBody = json_encode(['hook_id' => 1]);

    githubProbeRequest((string) $rawBody, signature: GitHubWebhookFixtures::signature((string) $rawBody, 'the-webhook-secret'))
        ->assertOk()
        ->assertJson(['ok' => true]);
});

it('rejects a request without a signature header', function (): void {
    githubProbeRequest('{"hook_id":1}')
        ->assertStatus(401)
        ->assertExactJson(['message' => 'Invalid signature.']);
});

it('rejects a request with a wrong signature', function (): void {
    $rawBody = json_encode(['hook_id' => 1]);

    githubProbeRequest((string) $rawBody, signature: GitHubWebhookFixtures::signature((string) $rawBody, 'another-secret'))
        ->assertStatus(401)
        ->assertExactJson(['message' => 'Invalid signature.']);
});

it('rejects a malformed signature without the sha256 prefix', function (): void {
    githubProbeRequest('{"hook_id":1}', signature: hash_hmac('sha256', '{"hook_id":1}', 'the-webhook-secret'))
        ->assertStatus(401)
        ->assertExactJson(['message' => 'Invalid signature.']);
});

it('fails closed when no webhook secret is configured', function (): void {
    config()->set('services.github.webhook_secret', null);

    $rawBody = json_encode(['hook_id' => 1]);

    githubProbeRequest((string) $rawBody, signature: GitHubWebhookFixtures::signature((string) $rawBody, 'the-webhook-secret'))
        ->assertStatus(401)
        ->assertExactJson(['message' => 'Invalid signature.']);
});
