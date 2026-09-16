<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 7 T5: promotions table for F4 Promotion Management.
     *
     * Single promo per product per time is enforced at the app layer
     * (PromotionService::assertNoOverlappingPromotion); this schema
     * carries only plain indexes so the migration stays portable
     * between sqlite (tests) and pgsql (prod).
     */
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('discount_type', ['percentage', 'fixed']);
            $table->decimal('discount_value', 12, 2);
            $table->decimal('max_discount', 12, 2)->nullable();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('min_order', 12, 2)->default(0);
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(true);
            $table->timestamp('broadcast_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'is_active']);
            $table->index(['start_date', 'end_date']);
        });

        // Promo snapshot on orders: applied promo id + discount amount.
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('promotion_id')->nullable()->constrained('promotions')->nullOnDelete();
            $table->decimal('discount_amount', 12, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promotion_id');
            $table->dropColumn('discount_amount');
        });

        Schema::dropIfExists('promotions');
    }
};
