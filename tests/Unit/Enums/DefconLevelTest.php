<?php

use App\Enums\DefconLevel;

it('defines the five defcon levels with integer values 1 to 5', function () {
    expect(DefconLevel::One->value)->toBe(1)
        ->and(DefconLevel::Two->value)->toBe(2)
        ->and(DefconLevel::Three->value)->toBe(3)
        ->and(DefconLevel::Four->value)->toBe(4)
        ->and(DefconLevel::Five->value)->toBe(5);
});

it('returns the label for each level', function () {
    expect(DefconLevel::One->label())->toBe('DEFCON 1')
        ->and(DefconLevel::Two->label())->toBe('DEFCON 2')
        ->and(DefconLevel::Three->label())->toBe('DEFCON 3')
        ->and(DefconLevel::Four->label())->toBe('DEFCON 4')
        ->and(DefconLevel::Five->label())->toBe('DEFCON 5');
});

it('resolves valid integer values through tryFrom and rejects invalid ones', function () {
    expect(DefconLevel::tryFrom(1))->toBe(DefconLevel::One)
        ->and(DefconLevel::tryFrom(5))->toBe(DefconLevel::Five)
        ->and(DefconLevel::tryFrom(0))->toBeNull()
        ->and(DefconLevel::tryFrom(6))->toBeNull();
});

it('implements BackedEnum with a value round-trip', function () {
    expect(DefconLevel::One)->toBeInstanceOf(BackedEnum::class);

    foreach (DefconLevel::cases() as $case) {
        expect(DefconLevel::from($case->value))->toBe($case);
    }
});
