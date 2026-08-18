<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Contributor risks: the tactical risk fingerprint of an author inside
     * an organization. score is bounded to 0-100 by a CHECK constraint
     * (Laravel 12 has no $table->check() builder method):
     *  - pgsql: named table-level constraint via ALTER TABLE;
     *  - sqlite: native DDL with an inline CHECK (sqlite cannot ALTER ADD
     *    CONSTRAINT, and inline CHECKs are enforced on INSERT).
     */
    public function up(): void
    {
        Schema::create('contributor_risks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id');
            $table->string('author_username');
            $table->unsignedTinyInteger('score');
            $table->unsignedInteger('total_prs')->default(0);
            $table->unsignedInteger('flagged_prs')->default(0);
            $table->unsignedInteger('hostile_prs')->default(0);
            $table->decimal('avg_findings_per_pr', 5, 2)->default(0);
            $table->boolean('is_new_contributor')->default(true);
            $table->timestamps();

            $table->unique(
                ['organization_id', 'author_username'],
                'contributor_risks_org_author_unique'
            );

            $table->foreign('organization_id', 'contributor_risks_org_foreign')
                ->references('id')->on('organizations')
                ->cascadeOnDelete();
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement(
                'ALTER TABLE contributor_risks ADD CONSTRAINT contributor_risks_score_check '
                .'CHECK (score >= 0 AND score <= 100)'
            );

            return;
        }

        // SQLite cannot ALTER TABLE ADD CONSTRAINT: recreate the table with
        // an inline CHECK so the validation also runs in the offline suite.
        if ($driver === 'sqlite') {
            Schema::drop('contributor_risks');
            DB::statement(<<<'SQL'
                CREATE TABLE contributor_risks (
                    id varchar primary key not null,
                    organization_id varchar not null references organizations(id) on delete cascade,
                    author_username varchar not null,
                    score integer not null check (score >= 0 and score <= 100),
                    total_prs integer default 0,
                    flagged_prs integer default 0,
                    hostile_prs integer default 0,
                    avg_findings_per_pr numeric default 0,
                    is_new_contributor integer default 1,
                    created_at datetime,
                    updated_at datetime,
                    unique (organization_id, author_username)
                )
                SQL);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contributor_risks');
    }
};
