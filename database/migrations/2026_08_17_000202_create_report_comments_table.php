<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Report comments: one GitHub comment per risk assessment, posted back
     * to the pull request by DiffOps. Append-only (no updated_at): a
     * comment is immutable evidence of what was reported. The unique
     * constraint guarantees one report per assessment.
     */
    public function up(): void
    {
        Schema::create('report_comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('risk_assessment_id');
            $table->unsignedBigInteger('github_comment_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(
                ['risk_assessment_id'],
                'report_comments_assessment_unique'
            );

            $table->foreign('risk_assessment_id', 'report_comments_assessment_foreign')
                ->references('id')->on('risk_assessments')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_comments');
    }
};
