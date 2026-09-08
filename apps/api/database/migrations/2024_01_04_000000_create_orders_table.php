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
            $table->string('idempotency_key')->nullable()->unique()->comment('Prevents duplicate order submissions');
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
