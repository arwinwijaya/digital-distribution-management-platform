<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Documents the new valid value 'promo_broadcast' for whatsapp_messages.message_type.
 *
 * The message_type column is a VARCHAR (string) with a default of 'text', not a Postgres enum.
 * No schema change is required — the application layer now accepts 'promo_broadcast' as a valid value.
 * This migration exists as a documentation marker and for traceability.
 */
return new class extends Migration
{
    public function up(): void
    {
        // No-op: message_type is a VARCHAR string column; 'promo_broadcast' is handled at the application layer.
    }

    public function down(): void
    {
        // No-op: nothing to reverse.
    }
};
