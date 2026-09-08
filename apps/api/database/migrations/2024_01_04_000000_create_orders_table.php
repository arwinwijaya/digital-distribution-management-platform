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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_id')->unique()->comment('Server-generated unique order identifier');
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('New');
            $table->decimal('total_amount', 14, 2)->default(0);
            // Snapshot the configured revenue rate used for this transaction.
            $table->decimal('commission_percentage', 5, 2)->default(2.00);
            // Always populated with the validated client key or a server-derived request identity.
            $table->string('idempotency_key', 64)->unique()->comment('Required request identity; prevents duplicate order submissions');
            $table->timestamps();

            $table->index(['outlet_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
