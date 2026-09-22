<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dateTime('pod_captured_at')->nullable()->after('proof_of_delivery');
            $table->decimal('pod_latitude', 10, 7)->nullable()->after('pod_captured_at');
            $table->decimal('pod_longitude', 10, 7)->nullable()->after('pod_latitude');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn(['pod_captured_at', 'pod_latitude', 'pod_longitude']);
        });
    }
};
