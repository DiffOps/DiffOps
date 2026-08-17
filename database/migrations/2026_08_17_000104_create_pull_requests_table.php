<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Pull requests ingested from GitHub, denormalized per organization.
     * repo_full_name + github_pr_number identify the PR inside the org.
     */
    public function up(): void
    {
        Schema::create('pull_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id');
            $table->unsignedBigInteger('github_repo_id')->nullable()->index();
            $table->string('repo_full_name');
            $table->unsignedBigInteger('github_pr_number');
            $table->string('title');
            $table->string('author_username')->index();
            $table->string('author_avatar_url')->nullable();
            $table->string('base_ref')->nullable();
            $table->string('head_ref')->nullable();
            $table->string('head_sha', 64)->nullable();
            $table->string('state', 10)->default('open');
            $table->boolean('is_draft')->default(false);
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['organization_id', 'repo_full_name', 'github_pr_number'],
                'pull_requests_org_repo_number_unique'
            );

            $table->foreign('organization_id', 'pull_requests_org_foreign')
                ->references('id')->on('organizations')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pull_requests');
    }
};
