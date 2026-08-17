<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Extends the scaffold users table with the DiffOps tactical profile:
     * Supabase identity (uid), GitHub handle/avatar and commander flag.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('supabase_uid')->nullable()->unique('users_supabase_uid_unique');
            $table->string('github_username')->nullable()->index();
            $table->string('avatar_url')->nullable();
            $table->boolean('is_commander')->default(false);
            $table->json('preferences')->nullable();
            $table->timestamp('last_login_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // drop the unique and the index explicitly before dropping the
            // columns — sqlite refuses DROP COLUMN while they still exist
            $table->dropUnique('users_supabase_uid_unique');
            $table->dropIndex('users_github_username_index');
            $table->dropColumn([
                'supabase_uid',
                'github_username',
                'avatar_url',
                'is_commander',
                'preferences',
                'last_login_at',
            ]);
        });
    }
};
