<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forecast_calibrations', function (Blueprint $table): void {
            $table->id();
            $table->string('dimension_key', 255)->unique();
            $table->decimal('bias_factor', 14, 6)->default(1);
            $table->decimal('seasonality_factor', 14, 6)->default(1);
            $table->string('method_version', 64);
            $table->boolean('fallback')->default(false);
            $table->unsignedInteger('sample_size')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecast_calibrations');
    }
};
