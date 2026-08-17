<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Organization members: users attached to an organization with a tactical
     * role (operator by default, commander promoted later).
     */
    public function up(): void
    {
        Schema::create('organization_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id');
            $table->unsignedBigInteger('user_id');
            $table->string('role', 20)->default('operator');
            $table->timestamps();

            $table->unique(['organization_id', 'user_id'], 'organization_members_org_user_unique');
            $table->index('user_id', 'organization_members_user_id_index');

            $table->foreign('organization_id', 'organization_members_org_foreign')
                ->references('id')->on('organizations')
                ->cascadeOnDelete();
            $table->foreign('user_id', 'organization_members_user_foreign')
                ->references('id')->on('users')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_members');
    }
};
