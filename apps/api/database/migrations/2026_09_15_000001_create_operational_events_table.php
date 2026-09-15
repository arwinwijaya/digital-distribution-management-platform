<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_events', function (Blueprint $table): void {
            $table->id();
            $table->string('correlation_id', 100);
            $table->string('route', 512);
            $table->string('action', 255)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->integer('status_code');
            $table->string('outcome', 16);
            $table->string('error_class', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->dateTime('occurred_at');
            $table->timestamps();
            $table->index('correlation_id', 'operational_events_correlation_id_idx');
            $table->index(['occurred_at'], 'operational_events_occurred_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_events');
    }
};
