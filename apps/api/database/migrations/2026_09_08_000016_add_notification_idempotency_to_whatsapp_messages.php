<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->string('logical_key', 128)->nullable()->after('provider_message_id');
            $table->string('provider_idempotency_key', 128)->nullable()->after('logical_key');
            $table->unique('logical_key', 'whatsapp_messages_logical_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropUnique('whatsapp_messages_logical_key_unique');
            $table->dropColumn(['logical_key', 'provider_idempotency_key']);
        });
    }
};
