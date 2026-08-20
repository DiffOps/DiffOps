<?php

namespace App\Services\Analysis;

/**
 * One slice of the sanitized diff, small enough to fit the AI context
 * window. Each chunk is analyzed by the LLM in a separate call.
 *
 * @immutable
 */
final class Chunk
{
    /**
     * @param  list<array{file_path: string, raw_patch: string, estimated_tokens: int}>  $files
     */
    public function __construct(
        public readonly array $files = [],
        public readonly int $estimated_tokens = 0,
        public readonly int $index = 0,
    ) {}
}
