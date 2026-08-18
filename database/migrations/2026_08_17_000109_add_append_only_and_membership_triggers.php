<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Database-level invariants that are impossible to express in the
     * offline sqlite suite:
     *  1. ai_decisions is append-only — any UPDATE or DELETE is rejected;
     *  2. organization_members bumps updated_at on every UPDATE (the app
     *     may not always go through Eloquent on the write path).
     *
     * pgsql-only: on any other driver (offline sqlite suite) this is a no-op.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver !== 'pgsql') {
            return;
        }

        DB::statement('DROP FUNCTION IF EXISTS fn_block_ai_decisions_write()');
        DB::statement(<<<'SQL'
            CREATE FUNCTION fn_block_ai_decisions_write()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION 'ai_decisions is append-only';
            END;
            $$
            SQL);

        DB::statement('DROP TRIGGER IF EXISTS trg_ai_decisions_append_only ON ai_decisions');
        DB::statement(<<<'SQL'
            CREATE TRIGGER trg_ai_decisions_append_only
            BEFORE UPDATE OR DELETE ON ai_decisions
            FOR EACH ROW
            EXECUTE FUNCTION fn_block_ai_decisions_write()
            SQL);

        DB::statement('DROP FUNCTION IF EXISTS fn_membership_touch_updated_at()');
        DB::statement(<<<'SQL'
            CREATE FUNCTION fn_membership_touch_updated_at()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                NEW.updated_at := now();
                RETURN NEW;
            END;
            $$
            SQL);

        DB::statement('DROP TRIGGER IF EXISTS trg_membership_touch_updated_at ON organization_members');
        DB::statement(<<<'SQL'
            CREATE TRIGGER trg_membership_touch_updated_at
            BEFORE UPDATE ON organization_members
            FOR EACH ROW
            EXECUTE FUNCTION fn_membership_touch_updated_at()
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

        DB::statement('DROP TRIGGER IF EXISTS trg_ai_decisions_append_only ON ai_decisions');
        DB::statement('DROP TRIGGER IF EXISTS trg_membership_touch_updated_at ON organization_members');
        DB::statement('DROP FUNCTION IF EXISTS fn_block_ai_decisions_write()');
        DB::statement('DROP FUNCTION IF EXISTS fn_membership_touch_updated_at()');
    }
};
