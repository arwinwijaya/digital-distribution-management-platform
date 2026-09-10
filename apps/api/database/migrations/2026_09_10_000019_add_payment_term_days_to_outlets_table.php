<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outlets', function (Blueprint $table): void {
            $table->unsignedInteger('payment_term_days')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('outlets', 'payment_term_days')) {
            Schema::table('outlets', function (Blueprint $table): void {
                $table->dropColumn('payment_term_days');
            });
        }
    }
};
