<?php

declare(strict_types=1);

use App\Enums\AiDecisionValidity;
use App\Services\Analysis\Chunk;
use App\Services\OpenRouter\OpenRouterService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Support\OpenRouterRateLimitFixtures;

beforeEach(function (): void {
    config()->set('services.openrouter', [
        'api_url' => 'https://openrouter.ai/api/v1',
        'api_key' => 'sk-or-v1-test-key',
        'model' => 'deepseek/deepseek-chat:free',
        'fallback_models' => ['qwen/qwen-2.5-72b-instruct:free'],
        'timeout' => 30,
        'retries' => 3,
        'max_tokens' => 1024,
        'temperature' => 0,
        'retry_base_ms' => 1,
        'circuit_threshold' => 3,
        'circuit_cooldown' => 60,
        'rate_limit_enabled' => true,
        'rpm' => 20,
        'rpm_per_model' => [],
    ]);

    Cache::flush();
    Http::preventStrayRequests();
});

function rlChunk(string $path = 'app/A.php', string $patch = "@@ -1,3 +1,4 @@\n+echo 'hi';\n"): Chunk
{
    return new Chunk([
        ['file_path' => $path, 'raw_patch' => $patch, 'estimated_tokens' => 3],
    ], 3, 0);
}

function rlCall(Chunk $chunk, int $chunkIndex = 1, int $chunkCount = 1): array
{
    return app(OpenRouterService::class)->call($chunk, $chunkIndex, $chunkCount);
}

it('does not exceed rpm within a burst', function (): void {
    config()->set('services.openrouter.rpm', 2);
    config()->set('services.openrouter.fallback_models', []);

    Http::fake([
        '*/chat/completions' => Http::response(OpenRouterRateLimitFixtures::validCompletion(), 200),
    ]);

    for ($i = 0; $i < 5; $i++) {
        rlCall(rlChunk());
    }

    Http::assertSentCount(2);
});

it('preserves fallback when primary bucket is empty', function (): void {
    config()->set('services.openrouter.rpm_per_model', [
        'deepseek/deepseek-chat:free' => 1,
        'qwen/qwen-2.5-72b-instruct:free' => 10,
    ]);

    Http::fake([
        '*/chat/completions' => Http::response(OpenRouterRateLimitFixtures::validCompletion(), 200),
    ]);

    $results = [
        rlCall(rlChunk()),
        rlCall(rlChunk()),
        rlCall(rlChunk()),
    ];

    Http::assertSentCount(3);

    $primary = 0;
    $fallback = 0;
    foreach (Http::recorded() as [$request]) {
        $model = $request->data()['model'];
        if ($model === 'deepseek/deepseek-chat:free') {
            $primary++;
        } elseif ($model === 'qwen/qwen-2.5-72b-instruct:free') {
            $fallback++;
        }
    }

    expect($primary)->toBe(1)
        ->and($fallback)->toBe(2);

    foreach ($results as $call) {
        expect($call[0]->validity)->toBe(AiDecisionValidity::Valid);
    }
});

it('still falls back on http 5xx without regression', function (): void {
    config()->set('services.openrouter.rpm_per_model', [
        'deepseek/deepseek-chat:free' => 1,
        'qwen/qwen-2.5-72b-instruct:free' => 10,
    ]);

    Http::fake(function (Request $request) {
        if ($request->data()['model'] === 'deepseek/deepseek-chat:free') {
            return Http::response(OpenRouterRateLimitFixtures::overloadedResponse(), 503);
        }

        return Http::response(OpenRouterRateLimitFixtures::validCompletion(), 200);
    });

    $results = rlCall(rlChunk());

    Http::assertSentCount(2);

    $recorded = Http::recorded();
    expect($recorded[0][0]->data()['model'])->toBe('deepseek/deepseek-chat:free')
        ->and($recorded[1][0]->data()['model'])->toBe('qwen/qwen-2.5-72b-instruct:free')
        ->and($results[count($results) - 1]->validity)->toBe(AiDecisionValidity::Valid);
});

it('is a noop when rate limit disabled', function (): void {
    config()->set('services.openrouter.rate_limit_enabled', false);
    config()->set('services.openrouter.rpm', 1);

    Http::fake([
        '*/chat/completions' => Http::response(OpenRouterRateLimitFixtures::validCompletion(), 200),
    ]);

    for ($i = 0; $i < 4; $i++) {
        rlCall(rlChunk());
    }

    Http::assertSentCount(4);
});

it('skips a model with rpm zero', function (): void {
    config()->set('services.openrouter.rpm_per_model', [
        'deepseek/deepseek-chat:free' => 0,
        'qwen/qwen-2.5-72b-instruct:free' => 10,
    ]);

    Http::fake([
        '*/chat/completions' => Http::response(OpenRouterRateLimitFixtures::validCompletion(), 200),
    ]);

    rlCall(rlChunk());

    Http::assertSentCount(1);

    $recorded = Http::recorded();
    expect($recorded[0][0]->data()['model'])->toBe('qwen/qwen-2.5-72b-instruct:free');
});
