<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Enables Row Level Security on every feature table and creates five
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

        DB::statement('ALTER TABLE repositories ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE report_comments ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE audit_logs ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE contributor_risks ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE repo_watchlist ENABLE ROW LEVEL SECURITY');

        DB::statement('DROP POLICY IF EXISTS "repositories_select" ON repositories');
        DB::statement(<<<'SQL'
            CREATE POLICY "repositories_select" ON repositories
            FOR SELECT TO authenticated
            USING (
                EXISTS (
                    SELECT 1
                    FROM organization_members AS om
                    JOIN users ON users.id = om.user_id
                    WHERE om.organization_id = repositories.organization_id
                      AND (
                          users.supabase_uid = auth.uid()
                          OR users.is_commander = true
                      )
                )
            )
            SQL);

        DB::statement('DROP POLICY IF EXISTS "report_comments_select" ON report_comments');
        DB::statement(<<<'SQL'
            CREATE POLICY "report_comments_select" ON report_comments
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
                    WHERE risk_assessments.id = report_comments.risk_assessment_id
                      AND (
                          users.supabase_uid = auth.uid()
                          OR users.is_commander = true
                      )
                )
            )
            SQL);

        DB::statement('DROP POLICY IF EXISTS "audit_logs_select" ON audit_logs');
        DB::statement(<<<'SQL'
            CREATE POLICY "audit_logs_select" ON audit_logs
            FOR SELECT TO authenticated
            USING (
                EXISTS (
                    SELECT 1
                    FROM users
                    WHERE users.id = audit_logs.user_id
                      AND users.supabase_uid = auth.uid()
                )
                OR EXISTS (
                    SELECT 1
                    FROM users
                    WHERE users.is_commander = true
                      AND users.supabase_uid = auth.uid()
                )
            )
            SQL);

        DB::statement('DROP POLICY IF EXISTS "contributor_risks_select" ON contributor_risks');
        DB::statement(<<<'SQL'
            CREATE POLICY "contributor_risks_select" ON contributor_risks
            FOR SELECT TO authenticated
            USING (
                EXISTS (
                    SELECT 1
                    FROM organization_members AS om
                    JOIN users ON users.id = om.user_id
                    WHERE om.organization_id = contributor_risks.organization_id
                      AND (
                          users.supabase_uid = auth.uid()
                          OR users.is_commander = true
                      )
                )
            )
            SQL);

        DB::statement('DROP POLICY IF EXISTS "repo_watchlist_select" ON repo_watchlist');
        DB::statement(<<<'SQL'
            CREATE POLICY "repo_watchlist_select" ON repo_watchlist
            FOR SELECT TO authenticated
            USING (
                EXISTS (
                    SELECT 1
                    FROM users
                    WHERE users.id = repo_watchlist.user_id
                      AND users.supabase_uid = auth.uid()
                )
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

        DB::statement('DROP POLICY IF EXISTS "repositories_select" ON repositories');
        DB::statement('DROP POLICY IF EXISTS "report_comments_select" ON report_comments');
        DB::statement('DROP POLICY IF EXISTS "audit_logs_select" ON audit_logs');
        DB::statement('DROP POLICY IF EXISTS "contributor_risks_select" ON contributor_risks');
        DB::statement('DROP POLICY IF EXISTS "repo_watchlist_select" ON repo_watchlist');

        DB::statement('ALTER TABLE repositories DISABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE report_comments DISABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE audit_logs DISABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE contributor_risks DISABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE repo_watchlist DISABLE ROW LEVEL SECURITY');
    }
};
