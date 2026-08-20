<?php

namespace App\Services\OpenRouter;

use App\Enums\AiDecisionValidity;

/**
 * Immutable evidence of a single HTTP call to the OpenRouter API.
 * The job persists one AiDecision row per AiCallResult (append-only).
 *
 * @immutable
 */
final class AiCallResult
{
    /**
     * @param  array<string, int>|null  $tokens
     * @param  array<string, mixed>|null  $parsed
     */
    public function __construct(
        public readonly string $model_used,
        public readonly int $attempt,
        public readonly AiDecisionValidity $validity,
        public readonly ?string $raw_response = null,
        public readonly ?array $parsed = null,
        public readonly ?array $tokens = null,
        public readonly ?int $latency_ms = null,
        public readonly bool $repaired = false,
        public readonly int $chunk_index = 0,
        public readonly int $chunk_count = 0,
        public readonly ?int $http_status = null,
    ) {}
}
