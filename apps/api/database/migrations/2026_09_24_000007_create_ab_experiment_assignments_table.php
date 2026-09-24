<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ab_experiment_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('experiment_id')->constrained('ab_experiments')->cascadeOnDelete();
            $table->string('subject_key', 255);
            $table->string('bucket', 32);
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamps();

            $table->unique(['experiment_id', 'subject_key'], 'ab_assignments_experiment_subject_unique');
            $table->index(['experiment_id', 'bucket']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ab_experiment_assignments');
    }
};
