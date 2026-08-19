<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessIncursionJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public array $payload,
        public ?string $deliveryId = null,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        //
    }
}
