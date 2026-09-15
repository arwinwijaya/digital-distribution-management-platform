<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Nullable for legacy orders that predate this column.
            // Stores the SHA-256 canonical fingerprint of the order items payload
            // so that same-key/different-payload idempotency conflicts can be
            // detected without re-parsing the original request.
            $table->string('idempotency_payload_hash', 64)->nullable()->after('idempotency_key')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['idempotency_payload_hash']);
            $table->dropColumn('idempotency_payload_hash');
        });
    }
};
