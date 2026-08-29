<?php

declare(strict_types=1);

use App\Services\OpenRouter\RateLimit\TokenBucket;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    Cache::flush();
    Carbon::setTestNow(null);
});

it('consumes a token when available', function (): void {
    $bucket = new TokenBucket('model-a', 3);

    expect($bucket->consumir())->toBeTrue()
        ->and($bucket->consumir())->toBeTrue()
        ->and($bucket->consumir())->toBeTrue()
        ->and($bucket->consumir())->toBeFalse();
});

it('refills tokens after the window elapses', function (): void {
    $bucket = new TokenBucket('model-a', 1);

    expect($bucket->consumir())->toBeTrue();

    Carbon::setTestNow(Carbon::now()->addSeconds(61));

    expect($bucket->consumir())->toBeTrue();

    Carbon::setTestNow(null);
});

it('keeps per model buckets independent', function (): void {
    $a = new TokenBucket('model-a', 1);
    $b = new TokenBucket('model-b', 5);

    expect($a->consumir())->toBeTrue();   // esgota A
    expect($a->saldoDisponivel())->toBe(0);

    // esgotar A não afeta B
    expect($b->saldoDisponivel())->toBe(5);
    expect($a->consumir())->toBeFalse();
    expect($b->consumir())->toBeTrue();
    expect($b->saldoDisponivel())->toBe(4);
});

it('never over consumes beyond capacity', function (): void {
    $bucket = new TokenBucket('model-a', 2);

    $allowed = 0;
    for ($i = 0; $i < 100; $i++) {
        if ($bucket->consumir()) {
            $allowed++;
        }
    }

    expect($allowed)->toBe(2);
});

it('reports seconds until availability', function (): void {
    $bucket = new TokenBucket('model-a', 1);

    expect($bucket->consumir())->toBeTrue();
    expect($bucket->saldoDisponivel())->toBe(0);
    expect($bucket->segundosParaDisponibilidade())->toBeGreaterThan(0);

    Carbon::setTestNow(Carbon::now()->addSeconds(61));

    expect($bucket->segundosParaDisponibilidade())->toBe(0);

    Carbon::setTestNow(null);
});
