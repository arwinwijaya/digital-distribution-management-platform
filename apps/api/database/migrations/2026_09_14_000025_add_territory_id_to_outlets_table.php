<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outlets', function (Blueprint $table): void {
            $table->foreignId('territory_id')->nullable()->after('user_id')->constrained('territories')->nullOnDelete();
            $table->index('territory_id');
        });
    }

    public function down(): void
    {
        Schema::table('outlets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('territory_id');
        });
    }
};
