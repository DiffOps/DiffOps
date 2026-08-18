<?php

use App\Enums\Verdict;

it('defines the clear, flagged and hostile cases with the expected values', function () {
    expect(Verdict::Clear->value)->toBe('clear')
        ->and(Verdict::Flagged->value)->toBe('flagged')
        ->and(Verdict::Hostile->value)->toBe('hostile');
});

it('returns the tactical label for each case', function () {
    expect(Verdict::Clear->label())->toBe('CLEAR')
        ->and(Verdict::Flagged->label())->toBe('FLAGGED')
        ->and(Verdict::Hostile->label())->toBe('HOSTILE');
});

it('resolves valid values through tryFrom and rejects invalid ones', function () {
    expect(Verdict::tryFrom('clear'))->toBe(Verdict::Clear)
        ->and(Verdict::tryFrom('flagged'))->toBe(Verdict::Flagged)
        ->and(Verdict::tryFrom('hostile'))->toBe(Verdict::Hostile)
        ->and(Verdict::tryFrom('blocked'))->toBeNull();
});

it('implements BackedEnum with a value round-trip', function () {
    expect(Verdict::Clear)->toBeInstanceOf(BackedEnum::class);

    foreach (Verdict::cases() as $case) {
        expect(Verdict::from($case->value))->toBe($case);
    }
});
