<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_reminders', function (Blueprint $table): void {
            $table->dateTime('claimed_at')->nullable()->after('status');
            $table->string('claim_token', 64)->nullable()->after('claimed_at');
            $table->index(['status', 'claimed_at'], 'invoice_reminders_send_lease_index');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_reminders', function (Blueprint $table): void {
            $table->dropIndex('invoice_reminders_send_lease_index');
            $table->dropColumn(['claimed_at', 'claim_token']);
        });
    }
};
