<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('product_name_snapshot')->nullable()->after('product_id');
        });

        // Backfill existing rows from products.name where snapshot is null.
        // Use correlated subquery for cross-driver compatibility (SQLite + PostgreSQL).
        DB::statement(<<<'SQL'
            UPDATE order_items
            SET product_name_snapshot = (
                SELECT name FROM products WHERE products.id = order_items.product_id
            )
            WHERE product_name_snapshot IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('product_name_snapshot');
        });
    }
};
