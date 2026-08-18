<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Audit logs: append-only security trail of every sensitive action.
     * user_id is nullable and SET NULL on user deletion (the log survives
     * as evidence even when the actor account is removed).
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action');
            $table->string('entity_type');
            $table->uuid('entity_id')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('user_id', 'audit_logs_user_foreign')
                ->references('id')->on('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
