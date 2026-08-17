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
     * Risk assessments: the core analysis output for a pull request head.
     * defcon_level is bounded to 1-5 and security_score to 0-100 by CHECK
     * constraints. Laravel 12 has no $table->check() builder method, so the
     * constraints are applied per driver:
     *  - pgsql: named table-level constraints via ALTER TABLE;
     *  - sqlite: native DDL with inline CHECKs (sqlite cannot ALTER ADD
     *    CONSTRAINT, and inline CHECKs are enforced on INSERT).
     */
    public function up(): void
    {
        Schema::create('risk_assessments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('pull_request_id');
            $table->string('head_sha', 64);
            $table->string('verdict', 10)->default('clear');
            $table->unsignedTinyInteger('defcon_level')->default(5);
            $table->unsignedTinyInteger('security_score')->default(0);
            $table->string('risk_level', 10)->default('low');
            $table->text('summary')->nullable();
            $table->json('compliance_checks')->nullable();
            $table->unsignedInteger('execution_time_ms')->nullable();
            $table->boolean('is_degraded')->default(false);
            $table->timestamps();

            $table->unique(['pull_request_id', 'head_sha'], 'risk_assessments_pr_sha_unique');

            $table->foreign('pull_request_id', 'risk_assessments_pr_foreign')
                ->references('id')->on('pull_requests')
                ->cascadeOnDelete();
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement(
                'ALTER TABLE risk_assessments ADD CONSTRAINT risk_assessments_defcon_check '
                .'CHECK (defcon_level >= 1 AND defcon_level <= 5)'
            );
            DB::statement(
                'ALTER TABLE risk_assessments ADD CONSTRAINT risk_assessments_security_score_check '
                .'CHECK (security_score >= 0 AND security_score <= 100)'
            );

            return;
        }

        // SQLite cannot ALTER TABLE ADD CONSTRAINT: recreate the table with
        // inline CHECKs so the validation also runs in the offline suite.
        if ($driver === 'sqlite') {
            Schema::drop('risk_assessments');
            DB::statement(<<<'SQL'
                CREATE TABLE risk_assessments (
                    id varchar primary key not null,
                    pull_request_id varchar not null references pull_requests(id) on delete cascade,
                    head_sha varchar not null,
                    verdict varchar default 'clear',
                    defcon_level integer default 5 check (defcon_level >= 1 and defcon_level <= 5),
                    security_score integer default 0 check (security_score >= 0 and security_score <= 100),
                    risk_level varchar default 'low',
                    summary text,
                    compliance_checks text,
                    execution_time_ms integer,
                    is_degraded integer default 0,
                    created_at datetime,
                    updated_at datetime,
                    unique (pull_request_id, head_sha)
                )
                SQL);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('risk_assessments');
    }
};
