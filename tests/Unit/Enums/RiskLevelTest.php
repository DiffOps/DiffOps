<?php

use App\Enums\RiskLevel;

it('defines the low, medium and high cases with the expected values', function () {
    expect(RiskLevel::Low->value)->toBe('low')
        ->and(RiskLevel::Medium->value)->toBe('medium')
        ->and(RiskLevel::High->value)->toBe('high');
});

it('returns the label for each case', function () {
    expect(RiskLevel::Low->label())->toBe('Baixo')
        ->and(RiskLevel::Medium->label())->toBe('Médio')
        ->and(RiskLevel::High->label())->toBe('Alto');
});

it('resolves valid values through tryFrom and rejects invalid ones', function () {
    expect(RiskLevel::tryFrom('low'))->toBe(RiskLevel::Low)
        ->and(RiskLevel::tryFrom('medium'))->toBe(RiskLevel::Medium)
        ->and(RiskLevel::tryFrom('high'))->toBe(RiskLevel::High)
        ->and(RiskLevel::tryFrom('critical'))->toBeNull();
});

it('implements BackedEnum with a value round-trip', function () {
    expect(RiskLevel::Low)->toBeInstanceOf(BackedEnum::class);

    foreach (RiskLevel::cases() as $case) {
        expect(RiskLevel::from($case->value))->toBe($case);
    }
});
