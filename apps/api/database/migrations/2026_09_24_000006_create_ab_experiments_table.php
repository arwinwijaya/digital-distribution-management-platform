<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ab_experiments', function (Blueprint $table): void {
            $table->id();
            $table->string('experiment_key', 128)->unique();
            $table->string('name');
            $table->string('status', 32)->default('draft');
            $table->unsignedInteger('minimum_sample_size')->default(30);
            $table->json('configuration')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ab_experiments');
    }
};
