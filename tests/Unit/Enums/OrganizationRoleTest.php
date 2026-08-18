<?php

use App\Enums\OrganizationRole;

it('defines the commander and operator cases with the expected values', function () {
    expect(OrganizationRole::Commander->value)->toBe('commander')
        ->and(OrganizationRole::Operator->value)->toBe('operator');
});

it('returns the tactical label for each case', function () {
    expect(OrganizationRole::Commander->label())->toBe('Comandante')
        ->and(OrganizationRole::Operator->label())->toBe('Operador');
});

it('resolves valid values through tryFrom and rejects invalid ones', function () {
    expect(OrganizationRole::tryFrom('commander'))->toBe(OrganizationRole::Commander)
        ->and(OrganizationRole::tryFrom('operator'))->toBe(OrganizationRole::Operator)
        ->and(OrganizationRole::tryFrom('admin'))->toBeNull();
});

it('implements BackedEnum with a value round-trip', function () {
    expect(OrganizationRole::Commander)->toBeInstanceOf(BackedEnum::class);

    foreach (OrganizationRole::cases() as $case) {
        expect(OrganizationRole::from($case->value))->toBe($case);
    }
});
