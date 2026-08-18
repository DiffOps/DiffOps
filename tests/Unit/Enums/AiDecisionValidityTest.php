<?php

use App\Enums\AiDecisionValidity;

it('defines the valid, repaired and failed cases with the expected values', function () {
    expect(AiDecisionValidity::Valid->value)->toBe('valid')
        ->and(AiDecisionValidity::Repaired->value)->toBe('repaired')
        ->and(AiDecisionValidity::Failed->value)->toBe('failed');
});

it('returns the label for each case', function () {
    expect(AiDecisionValidity::Valid->label())->toBe('Válida')
        ->and(AiDecisionValidity::Repaired->label())->toBe('Reparada')
        ->and(AiDecisionValidity::Failed->label())->toBe('Falhou');
});

it('resolves valid values through tryFrom and rejects invalid ones', function () {
    expect(AiDecisionValidity::tryFrom('valid'))->toBe(AiDecisionValidity::Valid)
        ->and(AiDecisionValidity::tryFrom('repaired'))->toBe(AiDecisionValidity::Repaired)
        ->and(AiDecisionValidity::tryFrom('failed'))->toBe(AiDecisionValidity::Failed)
        ->and(AiDecisionValidity::tryFrom('pending'))->toBeNull();
});

it('implements BackedEnum with a value round-trip', function () {
    expect(AiDecisionValidity::Valid)->toBeInstanceOf(BackedEnum::class);

    foreach (AiDecisionValidity::cases() as $case) {
        expect(AiDecisionValidity::from($case->value))->toBe($case);
    }
});
