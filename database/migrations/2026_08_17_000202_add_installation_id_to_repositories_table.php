<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The GitHub App installation id lets DiffOps obtain an installation
     * access token to post Recon Report comments back to the PR (F1).
     * Nullable: repositories registered before F1 have no installation yet.
     */
    public function up(): void
    {
        Schema::table('repositories', function (Blueprint $table) {
            $table->unsignedBigInteger('installation_id')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('repositories', function (Blueprint $table) {
            $table->dropIndex('repositories_installation_id_index');
            $table->dropColumn('installation_id');
        });
    }
};
