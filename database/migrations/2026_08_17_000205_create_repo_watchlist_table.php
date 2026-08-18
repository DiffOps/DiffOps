<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Repo watchlist: users tracking a repository for tactical incursions.
     * No surrogate id on purpose — the composite primary key (user_id,
     * repository_id) is the natural key and prevents duplicates. Append-only
     * (created_at only).
     */
    public function up(): void
    {
        Schema::create('repo_watchlist', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id');
            $table->foreignUuid('repository_id');
            $table->timestamp('created_at')->nullable();

            $table->primary(['user_id', 'repository_id'], 'repo_watchlist_pk');

            $table->foreign('user_id', 'repo_watchlist_user_foreign')
                ->references('id')->on('users')
                ->cascadeOnDelete();
            $table->foreign('repository_id', 'repo_watchlist_repository_foreign')
                ->references('id')->on('repositories')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('repo_watchlist');
    }
};
