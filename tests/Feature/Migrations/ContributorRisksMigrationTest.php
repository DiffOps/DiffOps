<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates contributor_risks with the expected columns', function () {
    expect(Schema::hasTable('contributor_risks'))->toBeTrue();

    $columns = array_column(Schema::getColumns('contributor_risks'), 'name');

    expect($columns)->toContain(
        'id', 'organization_id', 'author_username', 'score', 'total_prs',
        'flagged_prs', 'hostile_prs', 'avg_findings_per_pr', 'is_new_contributor',
        'created_at', 'updated_at'
    );
});

it('defaults counters, average and is_new_contributor', function () {
    $orgId = seedOrganizationForRisk();

    DB::table('contributor_risks')->insert([
        'id' => (string) Str::uuid(),
        'organization_id' => $orgId,
        'author_username' => 'dev',
        'score' => 25,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $risk = DB::table('contributor_risks')->where('author_username', 'dev')->first();

    expect((int) $risk->total_prs)->toBe(0)
        ->and((int) $risk->flagged_prs)->toBe(0)
        ->and((int) $risk->hostile_prs)->toBe(0)
        ->and((float) $risk->avg_findings_per_pr)->toBe(0.0)
        ->and((bool) $risk->is_new_contributor)->toBeTrue();
});

it('rejects a contributor risk without a score (not null)', function () {
    $orgId = seedOrganizationForRisk();

    DB::table('contributor_risks')->insert([
        'id' => (string) Str::uuid(),
        'organization_id' => $orgId,
        'author_username' => 'no-score',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
})->throws(QueryException::class);

it('rejects a score above 100 via check constraint', function () {
    $orgId = seedOrganizationForRisk();

    DB::table('contributor_risks')->insert([
        'id' => (string) Str::uuid(),
        'organization_id' => $orgId,
        'author_username' => 'too-high',
        'score' => 150,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
})->throws(QueryException::class);

it('rejects a negative score via check constraint', function () {
    $orgId = seedOrganizationForRisk();

    DB::table('contributor_risks')->insert([
        'id' => (string) Str::uuid(),
        'organization_id' => $orgId,
        'author_username' => 'negative',
        'score' => -5,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
})->throws(QueryException::class);

it('rejects duplicate (organization_id, author_username) pairs', function () {
    $orgId = seedOrganizationForRisk();

    foreach ([1, 2] as $i) {
        DB::table('contributor_risks')->insert([
            'id' => (string) Str::uuid(),
            'organization_id' => $orgId,
            'author_username' => 'duplicated',
            'score' => $i * 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
})->throws(QueryException::class);

it('round-trips avg_findings_per_pr as a decimal', function () {
    $orgId = seedOrganizationForRisk();

    DB::table('contributor_risks')->insert([
        'id' => (string) Str::uuid(),
        'organization_id' => $orgId,
        'author_username' => 'average',
        'score' => 40,
        'avg_findings_per_pr' => 12.34,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $risk = DB::table('contributor_risks')->where('author_username', 'average')->first();

    expect((float) $risk->avg_findings_per_pr)->toBe(12.34);
});

it('cascades contributor risks when the organization is deleted', function () {
    $orgId = seedOrganizationForRisk();

    DB::table('contributor_risks')->insert([
        'id' => (string) Str::uuid(),
        'organization_id' => $orgId,
        'author_username' => 'cascade',
        'score' => 60,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('organizations')->where('id', $orgId)->delete();

    expect(DB::table('contributor_risks')->count())->toBe(0);
});

it('rejects a contributor risk for a non-existent organization', function () {
    DB::table('contributor_risks')->insert([
        'id' => (string) Str::uuid(),
        'organization_id' => (string) Str::uuid(),
        'author_username' => 'missing-org',
        'score' => 30,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
})->throws(QueryException::class);

function seedOrganizationForRisk(): string
{
    $orgId = (string) Str::uuid();
    $now = now();

    DB::table('organizations')->insert([
        'id' => $orgId,
        'name' => 'Acme',
        'slug' => 'acme',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return $orgId;
}
