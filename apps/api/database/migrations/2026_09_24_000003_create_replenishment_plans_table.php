<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('replenishment_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('draft');
            $table->date('window_start');
            $table->date('window_end');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->json('execution_result')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['window_start', 'window_end', 'supplier_id'], 'replenishment_plans_window_supplier_unique');
            $table->index(['status', 'supplier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('replenishment_plans');
    }
};
