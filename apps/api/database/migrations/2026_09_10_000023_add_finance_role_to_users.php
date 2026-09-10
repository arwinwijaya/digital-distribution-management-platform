<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const WITH_FINANCE = ['admin', 'supplier', 'outlet', 'sales', 'driver', 'finance'];
    private const WITHOUT_FINANCE = ['admin', 'supplier', 'outlet', 'sales', 'driver'];

    public function up(): void
    {
        $this->replaceRoleConstraint(self::WITH_FINANCE);
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'finance')->update(['role' => 'outlet']);
        $this->replaceRoleConstraint(self::WITHOUT_FINANCE);
    }

    private function replaceRoleConstraint(array $allowedRoles): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            $this->replacePostgresRoleConstraint($allowedRoles);
            return;
        }

        Schema::table('users', function (Blueprint $table) use ($allowedRoles): void {
            $table->enum('role', $allowedRoles)->default('outlet')->change();
        });
    }

    private function replacePostgresRoleConstraint(array $allowedRoles): void
    {
        $constraints = DB::select(
            <<<'SQL'
            SELECT c.conname
            FROM pg_constraint c
            JOIN pg_class t ON t.oid = c.conrelid
            JOIN pg_namespace n ON n.oid = t.relnamespace
            JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = ANY (c.conkey)
            WHERE n.nspname = current_schema()
              AND t.relname = 'users'
              AND a.attname = 'role'
              AND c.contype = 'c'
            SQL
        );

        foreach ($constraints as $constraint) {
            DB::statement(sprintf(
                'ALTER TABLE "users" DROP CONSTRAINT "%s"',
                str_replace('"', '""', $constraint->conname),
            ));
        }

        $values = implode(', ', array_map(
            static fn (string $role): string => "'".str_replace("'", "''", $role)."'",
            $allowedRoles,
        ));

        DB::statement(
            'ALTER TABLE "users" ADD CONSTRAINT "users_role_check" CHECK ("role" IN ('.$values.'))'
        );
    }
};
