<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recommendation_events', function (Blueprint $table): void {
            $table->id();
            $table->string('event_uuid', 128)->unique();
            $table->string('event_type', 32);
            $table->foreignId('outlet_id')->nullable()->constrained('outlets')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->dateTime('occurred_at');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['event_type', 'occurred_at'], 'recommendation_events_type_occurred_idx');
            $table->index(['outlet_id', 'product_id', 'occurred_at'], 'recommendation_events_attribution_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendation_events');
    }
};
