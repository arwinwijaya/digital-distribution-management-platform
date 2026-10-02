<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // SQLite cannot ALTER TABLE to add a CHECK constraint. Guard both writes instead.
            DB::statement("CREATE TRIGGER singleton_must_be_x_insert BEFORE INSERT ON invoice_templates
                WHEN NEW.singleton != 'x' BEGIN SELECT RAISE(ABORT, 'singleton must be x'); END");
            DB::statement("CREATE TRIGGER singleton_must_be_x_update BEFORE UPDATE ON invoice_templates
                WHEN NEW.singleton != 'x' BEGIN SELECT RAISE(ABORT, 'singleton must be x'); END");

            return;
        }

        DB::statement("ALTER TABLE invoice_templates ADD CONSTRAINT singleton_must_be_x CHECK (singleton = 'x')");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP TRIGGER singleton_must_be_x_insert');
            DB::statement('DROP TRIGGER singleton_must_be_x_update');

            return;
        }

        DB::statement('ALTER TABLE invoice_templates DROP CONSTRAINT singleton_must_be_x');
    }
};
