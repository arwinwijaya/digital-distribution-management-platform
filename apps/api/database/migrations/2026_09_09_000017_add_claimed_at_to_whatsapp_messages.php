<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table): void {
            $table->timestamp('claimed_at')->nullable()->after('status');
            $table->index(['direction', 'status', 'claimed_at'], 'whatsapp_messages_delivery_lease_index');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table): void {
            $table->dropIndex('whatsapp_messages_delivery_lease_index');
            $table->dropColumn('claimed_at');
        });
    }
};
