<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_visits', function (Blueprint $table) {
            $table->dateTime('check_in_at')->nullable()->after('scheduled_at');
            $table->decimal('check_in_latitude', 10, 7)->nullable()->after('check_in_at');
            $table->decimal('check_in_longitude', 10, 7)->nullable()->after('check_in_latitude');
            $table->unsignedInteger('check_in_accuracy_m')->nullable()->after('check_in_longitude');
            $table->dateTime('check_out_at')->nullable()->after('check_in_accuracy_m');
            $table->decimal('check_out_latitude', 10, 7)->nullable()->after('check_out_at');
            $table->decimal('check_out_longitude', 10, 7)->nullable()->after('check_out_latitude');
        });
    }

    public function down(): void
    {
        Schema::table('sales_visits', function (Blueprint $table) {
            $table->dropColumn([
                'check_in_at',
                'check_in_latitude',
                'check_in_longitude',
                'check_in_accuracy_m',
                'check_out_at',
                'check_out_latitude',
                'check_out_longitude',
            ]);
        });
    }
};
