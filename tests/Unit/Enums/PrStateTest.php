<?php

use App\Enums\PrState;

it('defines the open, closed and merged cases with the expected values', function () {
    expect(PrState::Open->value)->toBe('open')
        ->and(PrState::Closed->value)->toBe('closed')
        ->and(PrState::Merged->value)->toBe('merged');
});

it('returns the label for each case', function () {
    expect(PrState::Open->label())->toBe('Aberta')
        ->and(PrState::Closed->label())->toBe('Fechada')
        ->and(PrState::Merged->label())->toBe('Mergeada');
});

it('resolves valid values through tryFrom and rejects invalid ones', function () {
    expect(PrState::tryFrom('open'))->toBe(PrState::Open)
        ->and(PrState::tryFrom('closed'))->toBe(PrState::Closed)
        ->and(PrState::tryFrom('merged'))->toBe(PrState::Merged)
        ->and(PrState::tryFrom('draft'))->toBeNull();
});

it('implements BackedEnum with a value round-trip', function () {
    expect(PrState::Open)->toBeInstanceOf(BackedEnum::class);

    foreach (PrState::cases() as $case) {
        expect(PrState::from($case->value))->toBe($case);
    }
});
