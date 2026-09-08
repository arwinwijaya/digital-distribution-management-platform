<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('payment_method', 32);
            $table->string('status', 32)->default('completed');
            $table->string('idempotency_key', 64)->unique();
            $table->string('receipt_reference', 64)->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index(['outlet_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
