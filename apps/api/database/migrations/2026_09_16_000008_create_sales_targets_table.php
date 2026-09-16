<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 7 T6: sales targets for F5 Sales Order Collection & Performance.
     *
     * One target row per sales user per monthly period (YYYY-MM). The period
     * is stored as a 7-character string so the unique constraint is portable
     * between sqlite (tests) and pgsql (prod).
     *
     * NOTE: `orders.sales_user_id` (nullable FK + index) was already added by
     * migration 2026_09_16_000005_add_sales_user_id_to_orders_table.php, so
     * this migration deliberately does NOT add it again.
     */
    public function up(): void
    {
        Schema::create('sales_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('period', 7);
            $table->decimal('target_amount', 14, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'period']);
            $table->index('period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_targets');
    }
};
