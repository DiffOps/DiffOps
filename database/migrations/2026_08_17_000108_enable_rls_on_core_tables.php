<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Enables Row Level Security on every core table and creates seven
     * SELECT-only policies for the Supabase `authenticated` role. All
     * membership checks go through `organization_members` + `users` and
     * match on `users.supabase_uid = auth.uid()` — never on a raw user_id.
     *
     * pgsql-only: on any other driver (offline sqlite suite) this is a no-op.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE organizations ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE organization_members ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE pull_requests ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE pull_request_files ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE risk_assessments ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE ai_decisions ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE users ENABLE ROW LEVEL SECURITY');

        DB::statement('DROP POLICY IF EXISTS "organizations_select" ON organizations');
        DB::statement(<<<'SQL'
            CREATE POLICY "organizations_select" ON organizations
            FOR SELECT TO authenticated
            USING (
                EXISTS (
                    SELECT 1
                    FROM organization_members AS om
                    JOIN users ON users.id = om.user_id
                    WHERE om.organization_id = organizations.id
                      AND (
                          users.supabase_uid = auth.uid()
                          OR users.is_commander = true
                      )
                )
            )
            SQL);

        DB::statement('DROP POLICY IF EXISTS "organization_members_select" ON organization_members');
        DB::statement(<<<'SQL'
            CREATE POLICY "organization_members_select" ON organization_members
            FOR SELECT TO authenticated
            USING (
                EXISTS (
                    SELECT 1
                    FROM users
                    WHERE users.id = organization_members.user_id
                      AND users.supabase_uid = auth.uid()
                )
                OR EXISTS (
                    SELECT 1
                    FROM organization_members AS om
                    JOIN users ON users.id = om.user_id
                    WHERE om.organization_id = organization_members.organization_id
                      AND users.supabase_uid = auth.uid()
                      AND users.is_commander = true
                )
            )
            SQL);

        DB::statement('DROP POLICY IF EXISTS "pull_requests_select" ON pull_requests');
        DB::statement(<<<'SQL'
            CREATE POLICY "pull_requests_select" ON pull_requests
            FOR SELECT TO authenticated
            USING (
                EXISTS (
                    SELECT 1
                    FROM organization_members AS om
                    JOIN users ON users.id = om.user_id
                    WHERE om.organization_id = pull_requests.organization_id
                      AND (
                          users.supabase_uid = auth.uid()
                          OR users.is_commander = true
                      )
                )
            )
            SQL);

        DB::statement('DROP POLICY IF EXISTS "pull_request_files_select" ON pull_request_files');
        DB::statement(<<<'SQL'
            CREATE POLICY "pull_request_files_select" ON pull_request_files
            FOR SELECT TO authenticated
            USING (
                EXISTS (
                    SELECT 1
                    FROM pull_requests
                    JOIN organization_members AS om
                      ON om.organization_id = pull_requests.organization_id
                    JOIN users ON users.id = om.user_id
                    WHERE pull_requests.id = pull_request_files.pull_request_id
                      AND (
                          users.supabase_uid = auth.uid()
                          OR users.is_commander = true
                      )
                )
            )
            SQL);

        DB::statement('DROP POLICY IF EXISTS "risk_assessments_select" ON risk_assessments');
        DB::statement(<<<'SQL'
            CREATE POLICY "risk_assessments_select" ON risk_assessments
            FOR SELECT TO authenticated
            USING (
                EXISTS (
                    SELECT 1
                    FROM pull_requests
                    JOIN organization_members AS om
                      ON om.organization_id = pull_requests.organization_id
                    JOIN users ON users.id = om.user_id
                    WHERE pull_requests.id = risk_assessments.pull_request_id
                      AND (
                          users.supabase_uid = auth.uid()
                          OR users.is_commander = true
                      )
                )
            )
            SQL);

        DB::statement('DROP POLICY IF EXISTS "ai_decisions_select" ON ai_decisions');
        DB::statement(<<<'SQL'
            CREATE POLICY "ai_decisions_select" ON ai_decisions
            FOR SELECT TO authenticated
            USING (
                EXISTS (
                    SELECT 1
                    FROM risk_assessments
                    JOIN pull_requests
                      ON pull_requests.id = risk_assessments.pull_request_id
                    JOIN organization_members AS om
                      ON om.organization_id = pull_requests.organization_id
                    JOIN users ON users.id = om.user_id
                    WHERE risk_assessments.id = ai_decisions.risk_assessment_id
                      AND (
                          users.supabase_uid = auth.uid()
                          OR users.is_commander = true
                      )
                )
            )
            SQL);

        DB::statement('DROP POLICY IF EXISTS "users_select_self" ON users');
        DB::statement(<<<'SQL'
            CREATE POLICY "users_select_self" ON users
            FOR SELECT TO authenticated
            USING (
                supabase_uid = auth.uid()
                OR is_commander = true
            )
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver !== 'pgsql') {
            return;
        }

        DB::statement('DROP POLICY IF EXISTS "organizations_select" ON organizations');
        DB::statement('DROP POLICY IF EXISTS "organization_members_select" ON organization_members');
        DB::statement('DROP POLICY IF EXISTS "pull_requests_select" ON pull_requests');
        DB::statement('DROP POLICY IF EXISTS "pull_request_files_select" ON pull_request_files');
        DB::statement('DROP POLICY IF EXISTS "risk_assessments_select" ON risk_assessments');
        DB::statement('DROP POLICY IF EXISTS "ai_decisions_select" ON ai_decisions');
        DB::statement('DROP POLICY IF EXISTS "users_select_self" ON users');

        DB::statement('ALTER TABLE organizations DISABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE organization_members DISABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE pull_requests DISABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE pull_request_files DISABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE risk_assessments DISABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE ai_decisions DISABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE users DISABLE ROW LEVEL SECURITY');
    }
};
