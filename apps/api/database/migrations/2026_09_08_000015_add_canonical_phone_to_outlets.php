<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->string('canonical_phone', 32)->nullable()->after('phone');
        });

        $seen = [];
        DB::table('outlets')->orderBy('id')->get(['id', 'phone'])->each(function (object $outlet) use (&$seen): void {
            $canonical = $this->canonicalize((string) $outlet->phone);
            // Keep the first legacy row deterministic and leave later conflicts
            // nullable. Their original phone remains intact and the resolver
            // still detects them as ambiguous until an operator repairs them.
            if ($canonical === '' || isset($seen[$canonical])) {
                DB::table('outlets')->where('id', $outlet->id)->update(['canonical_phone' => null]);

                return;
            }
            $seen[$canonical] = true;
            DB::table('outlets')->where('id', $outlet->id)->update(['canonical_phone' => $canonical]);
        });

        Schema::table('outlets', function (Blueprint $table) {
            $table->unique('canonical_phone', 'outlets_canonical_phone_unique');
        });
    }

    public function down(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->dropUnique('outlets_canonical_phone_unique');
            $table->dropColumn('canonical_phone');
        });
    }

    private function canonicalize(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }

        return $digits === '' ? '' : '+'.$digits;
    }
};
