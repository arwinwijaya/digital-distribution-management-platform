<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'orders_analytics_status_created_at_index');
        });

        Schema::table('outlets', function (Blueprint $table) {
            $table->index('is_active', 'outlets_analytics_active_index');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index('is_active', 'products_analytics_active_index');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_analytics_status_created_at_index');
        });

        Schema::table('outlets', function (Blueprint $table) {
            $table->dropIndex('outlets_analytics_active_index');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_analytics_active_index');
        });
    }
};
