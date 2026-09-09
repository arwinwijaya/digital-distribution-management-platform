<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->index(['subscription_status', 'name', 'id'], 'suppliers_marketplace_status_name_index');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index(['is_active', 'supplier_id', 'name', 'id'], 'products_marketplace_listing_index');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index(['created_at', 'id'], 'orders_latest_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_marketplace_listing_index');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_latest_created_at_index');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropIndex('suppliers_marketplace_status_name_index');
        });
    }
};
