<?php

use App\Models\ContributorRisk;
use App\Models\Organization;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('generates a uuid primary key on creation', function () {
    $risk = createContributorRiskRecord();

    expect($risk->id)->toBeString()
        ->and(strlen($risk->id))->toBe(36)
        ->and(ContributorRisk::query()->whereKey($risk->id)->exists())->toBeTrue();
});

it('accepts the risk fingerprint through mass assignment', function () {
    $risk = createContributorRiskRecord([
        'score' => 85,
        'total_prs' => 12,
        'flagged_prs' => 3,
        'hostile_prs' => 1,
        'avg_findings_per_pr' => 2.5,
        'is_new_contributor' => false,
    ]);

    expect($risk->score)->toBe(85)
        ->and($risk->total_prs)->toBe(12)
        ->and($risk->flagged_prs)->toBe(3)
        ->and($risk->hostile_prs)->toBe(1)
        ->and($risk->avg_findings_per_pr)->toBe('2.50')
        ->and($risk->is_new_contributor)->toBeFalse();
});

it('casts score, average findings and new contributor flag with raw round-trip', function () {
    $risk = createContributorRiskRecord([
        'score' => 42,
        'avg_findings_per_pr' => 3.5,
        'is_new_contributor' => true,
    ]);

    expect($risk->score)->toBeInt()
        ->and($risk->score)->toBe(42)
        ->and($risk->avg_findings_per_pr)->toBe('3.50')
        ->and($risk->is_new_contributor)->toBeTrue()
        ->and((float) $risk->getRawOriginal('avg_findings_per_pr'))->toBe(3.5)
        ->and((int) $risk->getRawOriginal('score'))->toBe(42)
        ->and((int) $risk->getRawOriginal('is_new_contributor'))->toBe(1);
});

it('belongs to an organization', function () {
    $risk = createContributorRiskRecord();

    expect($risk->organization)->toBeInstanceOf(Organization::class);
});

it('rejects a score above 100', function () {
    createContributorRiskRecord(['score' => 150]);
})->throws(QueryException::class);

it('rejects a negative score', function () {
    createContributorRiskRecord(['score' => -5]);
})->throws(QueryException::class);

it('enforces the unique organization and author pair', function () {
    createContributorRiskRecord(['author_username' => 'devacme']);

    createContributorRiskRecord(['author_username' => 'devacme']);
})->throws(QueryException::class);

function createContributorRiskRecord(array $overrides = []): ContributorRisk
{
    $organization = Organization::create(['name' => 'Acme', 'slug' => 'acme']);

    return ContributorRisk::create(array_merge([
        'organization_id' => $organization->id,
        'author_username' => 'devacme',
        'score' => 30,
    ], $overrides));
}
