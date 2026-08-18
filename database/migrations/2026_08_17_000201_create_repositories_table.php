<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Repositories registered by an organization: the tactical config for
     * each GitHub repo ingested by DiffOps (privacy, comment/post and
     * hostile-escalation flags). full_name + github_repo_id are unique
     * per organization.
     */
    public function up(): void
    {
        Schema::create('repositories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id');
            $table->unsignedBigInteger('github_repo_id');
            $table->string('full_name');
            $table->boolean('is_private')->default(false);
            $table->boolean('comment_on_pr')->default(false);
            $table->boolean('escalate_on_hostile')->default(false);
            $table->string('escalation_webhook_url')->nullable();
            $table->timestamps();

            $table->unique(
                ['organization_id', 'github_repo_id'],
                'repositories_org_repo_id_unique'
            );
            $table->unique(
                ['organization_id', 'full_name'],
                'repositories_org_full_name_unique'
            );

            $table->foreign('organization_id', 'repositories_org_foreign')
                ->references('id')->on('organizations')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('repositories');
    }
};
