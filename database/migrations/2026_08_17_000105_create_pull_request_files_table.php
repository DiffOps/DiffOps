<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Files touched by a pull request, with diff statistics and raw patch
     * for local heuristics and sensitive-file detection.
     */
    public function up(): void
    {
        Schema::create('pull_request_files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('pull_request_id');
            $table->string('file_path');
            $table->string('status', 20)->nullable();
            $table->unsignedInteger('additions')->default(0);
            $table->unsignedInteger('deletions')->default(0);
            $table->unsignedInteger('bytes')->default(0);
            $table->boolean('is_sensitive')->default(false);
            $table->boolean('is_binary')->default(false);
            $table->text('raw_patch')->nullable();
            $table->timestamps();

            $table->unique(['pull_request_id', 'file_path'], 'pull_request_files_pr_path_unique');

            $table->foreign('pull_request_id', 'pull_request_files_pr_foreign')
                ->references('id')->on('pull_requests')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pull_request_files');
    }
};
