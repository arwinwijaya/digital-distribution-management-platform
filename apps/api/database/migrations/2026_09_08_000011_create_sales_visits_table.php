<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('target')->nullable();
            $table->date('visit_date');
            $table->dateTime('scheduled_at')->nullable();
            $table->string('status')->default('planned');
            $table->text('notes')->nullable();
            $table->text('outcome')->nullable();
            $table->timestamps();

            $table->index(['sales_user_id', 'visit_date']);
            $table->index(['status', 'visit_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_visits');
    }
};
