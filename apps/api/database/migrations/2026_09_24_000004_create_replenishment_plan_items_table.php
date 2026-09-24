<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('replenishment_plan_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('replenishment_plan_id')->constrained('replenishment_plans')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->decimal('reorder_quantity', 14, 3)->default(0);
            $table->string('data_sufficiency', 32)->default('sufficient');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['replenishment_plan_id', 'product_id'], 'replenishment_plan_items_plan_product_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('replenishment_plan_items');
    }
};
