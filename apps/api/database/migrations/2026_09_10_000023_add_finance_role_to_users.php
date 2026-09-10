<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->enum('role', ['admin', 'supplier', 'outlet', 'sales', 'driver', 'finance'])
                ->default('outlet')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->enum('role', ['admin', 'supplier', 'outlet', 'sales', 'driver'])
                ->default('outlet')
                ->change();
        });
    }
};
