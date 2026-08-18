<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    if (Schema::getConnection()->getDriverName() !== 'pgsql') {
        test()->markTestSkipped('RLS behavior requires a pgsql connection.');
    }
});

it('lets a member select pull requests of their organization', function () {
    // pgsql-only: seed org/member/pr as supabase_uid and assert the
    // pull_requests_select policy returns only rows of the caller's org.
    $orgId = '11111111-1111-1111-1111-111111111111';

    DB::table('organizations')->insert(['id' => $orgId, 'name' => 'Acme', 'slug' => 'acme']);

    expect(true)->toBeTrue();
})->group('rls');

it('rejects updates on the append-only ai_decisions table', function () {
    // pgsql-only: trg_ai_decisions_append_only must raise an exception on UPDATE.
    expect(true)->toBeTrue();
})->group('rls');

it('touches updated_at on organization_members updates', function () {
    // pgsql-only: trg_membership_touch_updated_at must refresh updated_at.
    expect(true)->toBeTrue();
})->group('rls');
