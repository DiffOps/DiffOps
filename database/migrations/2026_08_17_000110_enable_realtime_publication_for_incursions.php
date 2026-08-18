<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Streams incursion data (pull_requests + risk_assessments) through the
     * Supabase Realtime publication. The DO block makes this idempotent:
     * tables are added only when the publication exists and only when they
     * are not already members of it.
     *
     * pgsql-only: on any other driver (offline sqlite suite) this is a no-op.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            DO $$
            BEGIN
                IF EXISTS (SELECT 1 FROM pg_publication WHERE pubname = 'supabase_realtime') THEN
                    IF NOT EXISTS (
                        SELECT 1 FROM pg_publication_tables
                        WHERE pubname = 'supabase_realtime' AND tablename = 'pull_requests'
                    ) THEN
                        ALTER PUBLICATION supabase_realtime ADD TABLE pull_requests;
                    END IF;

                    IF NOT EXISTS (
                        SELECT 1 FROM pg_publication_tables
                        WHERE pubname = 'supabase_realtime' AND tablename = 'risk_assessments'
                    ) THEN
                        ALTER PUBLICATION supabase_realtime ADD TABLE risk_assessments;
                    END IF;
                END IF;
            END
            $$
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

        DB::statement(<<<'SQL'
            DO $$
            BEGIN
                IF EXISTS (SELECT 1 FROM pg_publication WHERE pubname = 'supabase_realtime') THEN
                    IF EXISTS (
                        SELECT 1 FROM pg_publication_tables
                        WHERE pubname = 'supabase_realtime' AND tablename = 'pull_requests'
                    ) THEN
                        ALTER PUBLICATION supabase_realtime DROP TABLE pull_requests;
                    END IF;

                    IF EXISTS (
                        SELECT 1 FROM pg_publication_tables
                        WHERE pubname = 'supabase_realtime' AND tablename = 'risk_assessments'
                    ) THEN
                        ALTER PUBLICATION supabase_realtime DROP TABLE risk_assessments;
                    END IF;
                END IF;
            END
            $$
            SQL);
    }
};
