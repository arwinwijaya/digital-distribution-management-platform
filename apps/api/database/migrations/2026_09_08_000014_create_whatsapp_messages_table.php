<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->string('provider_message_id')->nullable()->unique();
            $table->string('direction');
            $table->string('phone')->nullable();
            $table->string('message_type')->default('text');
            $table->text('body')->nullable();
            $table->json('payload')->nullable();
            $table->string('status')->default('received');
            $table->text('error')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->foreignId('outlet_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['phone', 'direction']);
            $table->index(['status', 'direction']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
