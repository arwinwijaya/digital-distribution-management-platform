<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revenue_lift_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('experiment_id')->nullable()->constrained('ab_experiments')->nullOnDelete();
            $table->decimal('uplift', 14, 6)->nullable();
            $table->string('status', 32)->default('insufficient-data');
            $table->string('method_version', 64);
            $table->unsignedInteger('control_sample_size')->default(0);
            $table->unsignedInteger('treatment_sample_size')->default(0);
            $table->decimal('control_revenue', 16, 2)->default(0);
            $table->decimal('treatment_revenue', 16, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['experiment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revenue_lift_snapshots');
    }
};
