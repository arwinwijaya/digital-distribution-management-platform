<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('orders', 'commission_percentage')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->decimal('commission_percentage', 5, 2)
                    ->default(2.00)
                    ->after('total_amount');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'commission_percentage')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropColumn('commission_percentage');
            });
        }
    }
};
