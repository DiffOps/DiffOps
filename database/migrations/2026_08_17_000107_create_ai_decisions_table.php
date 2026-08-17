<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * AI decisions: append-only log of every model attempt on a risk
     * assessment. No updated_at on purpose — rows are immutable evidence.
     */
    public function up(): void
    {
        Schema::create('ai_decisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('risk_assessment_id');
            $table->string('model_used');
            $table->unsignedTinyInteger('attempt')->default(1);
            $table->string('validity', 10)->default('valid');
            $table->text('raw_response')->nullable();
            $table->json('ai_signals')->nullable();
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->unsignedInteger('total_tokens')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('risk_assessment_id', 'ai_decisions_assessment_foreign')
                ->references('id')->on('risk_assessments')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_decisions');
    }
};
