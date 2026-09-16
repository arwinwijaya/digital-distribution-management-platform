<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('territory_id')->nullable()->constrained()->nullOnDelete()->after('role');
            $table->index('territory_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['territory_id']);
            $table->dropIndex(['territory_id']);
            $table->dropColumn('territory_id');
        });
    }
};
