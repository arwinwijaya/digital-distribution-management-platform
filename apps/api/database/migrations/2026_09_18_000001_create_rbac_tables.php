<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RBAC Menu Access Matrix (2026-09-18).
 *
 * Two standalone tables:
 *  - menu_definitions: reference data (menu key -> label/group/sort order)
 *  - role_menu_access: the mutable matrix (role x menu_key -> none|read|edit)
 *
 * Additive only — no existing table is touched. Portable between sqlite
 * (tests, :memory:) and pgsql (runtime): no DB-specific types, plain
 * composite primary key, plain index for reverse lookups.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_definitions', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('label');
            $table->string('group');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('role_menu_access', function (Blueprint $table) {
            $table->string('role');
            $table->string('menu_key');
            $table->string('level')->default('none');
            $table->timestamps();

            $table->primary(['role', 'menu_key']);
            $table->foreign('menu_key')
                ->references('key')
                ->on('menu_definitions')
                ->cascadeOnDelete();
            $table->index('menu_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_menu_access');
        Schema::dropIfExists('menu_definitions');
    }
};
