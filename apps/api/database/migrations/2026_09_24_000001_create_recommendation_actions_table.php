<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recommendation_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_event_id')->nullable()->constrained('recommendation_events')->nullOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained('outlets')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('executed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 64);
            $table->string('status', 32)->default('draft');
            $table->json('payload');
            $table->string('idempotency_key', 128)->unique();
            $table->string('idempotency_payload_hash', 64)->index();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->json('execution_result')->nullable();
            $table->string('method', 64)->nullable();
            $table->string('method_version', 64)->nullable();
            $table->boolean('fallback')->default(false);
            $table->string('data_sufficiency', 32)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'type']);
            $table->index(['outlet_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendation_actions');
    }
};
