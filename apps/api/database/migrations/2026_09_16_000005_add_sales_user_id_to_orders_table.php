<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('sales_user_id')->nullable()->constrained('users')->nullOnDelete()->after('outlet_id');
            $table->index('sales_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['sales_user_id']);
            $table->dropIndex(['sales_user_id']);
            $table->dropColumn('sales_user_id');
        });
    }
};
