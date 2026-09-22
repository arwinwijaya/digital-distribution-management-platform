<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->string('vehicle_type')->nullable();
            $table->string('plate_number')->nullable();
            $table->unsignedInteger('capacity_kg')->nullable();
            $table->foreignId('service_territory_id')->nullable()->constrained('territories')->nullOnDelete();
            $table->time('shift_start')->nullable();
            $table->time('shift_end')->nullable();
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            $table->index(['is_available', 'service_territory_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_profiles');
    }
};
