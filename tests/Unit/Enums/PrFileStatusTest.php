<?php

use App\Enums\PrFileStatus;

it('defines the four file status cases with the expected values', function () {
    expect(PrFileStatus::Added->value)->toBe('added')
        ->and(PrFileStatus::Modified->value)->toBe('modified')
        ->and(PrFileStatus::Removed->value)->toBe('removed')
        ->and(PrFileStatus::Renamed->value)->toBe('renamed');
});

it('returns the label for each case', function () {
    expect(PrFileStatus::Added->label())->toBe('Adicionado')
        ->and(PrFileStatus::Modified->label())->toBe('Modificado')
        ->and(PrFileStatus::Removed->label())->toBe('Removido')
        ->and(PrFileStatus::Renamed->label())->toBe('Renomeado');
});

it('resolves valid values through tryFrom and rejects invalid ones', function () {
    expect(PrFileStatus::tryFrom('added'))->toBe(PrFileStatus::Added)
        ->and(PrFileStatus::tryFrom('modified'))->toBe(PrFileStatus::Modified)
        ->and(PrFileStatus::tryFrom('removed'))->toBe(PrFileStatus::Removed)
        ->and(PrFileStatus::tryFrom('renamed'))->toBe(PrFileStatus::Renamed)
        ->and(PrFileStatus::tryFrom('copied'))->toBeNull();
});

it('implements BackedEnum with a value round-trip', function () {
    expect(PrFileStatus::Added)->toBeInstanceOf(BackedEnum::class);

    foreach (PrFileStatus::cases() as $case) {
        expect(PrFileStatus::from($case->value))->toBe($case);
    }
});
