<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('driver_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_by_id')->constrained('users')->restrictOnDelete();
            $table->string('status')->default('assigned');
            $table->dateTime('assigned_at')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('proof_of_delivery_url')->nullable();
            $table->json('proof_of_delivery')->nullable();
            $table->text('notes')->nullable();
            $table->json('route_data')->nullable();
            $table->timestamps();

            $table->unique('order_id');
            $table->index(['driver_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
