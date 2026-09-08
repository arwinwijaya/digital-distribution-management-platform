<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Analytics filters completed payments by status/date and joins each
            // row to its parent order to apply the order-status exclusion rule.
            $table->index(
                ['status', 'created_at', 'order_id'],
                'payments_analytics_status_created_at_order_id_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_analytics_status_created_at_order_id_index');
        });
    }
};
