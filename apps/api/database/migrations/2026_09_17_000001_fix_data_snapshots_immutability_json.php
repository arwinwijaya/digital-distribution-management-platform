<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fixes the immutability guard on PostgreSQL.
 *
 * The original guard (2026_09_14_000027) compared the `lineage` column with
 * `NEW.lineage IS DISTINCT FROM OLD.lineage`. On PostgreSQL the column type is
 * `json`, and there is no equality operator for `json` (only `jsonb`), so the
 * trigger raised:
 *
 *   SQLSTATE[42883]: operator does not exist: json = json
 *
 * The guard only fires on UPDATE of a published snapshot, so the bug surfaced as
 * every `data:pipeline` run failing to publish (and ~27 CI test failures).
 * Recreating the function with an explicit `::jsonb` cast fixes it without
 * changing the column type. Fresh installs get the fixed version directly from
 * 2026_09_14_000027; this migration repairs databases that were already migrated.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION data_snapshots_published_immutable_guard() RETURNS trigger
            LANGUAGE plpgsql AS $$
            BEGIN
                IF OLD.status = 'published' AND (
                    NEW.run_id         IS DISTINCT FROM OLD.run_id OR
                    NEW.snapshot_uuid  IS DISTINCT FROM OLD.snapshot_uuid OR
                    NEW.version        IS DISTINCT FROM OLD.version OR
                    NEW.status         IS DISTINCT FROM OLD.status OR
                    NEW.window_start   IS DISTINCT FROM OLD.window_start OR
                    NEW.window_end     IS DISTINCT FROM OLD.window_end OR
                    NEW.timezone       IS DISTINCT FROM OLD.timezone OR
                    NEW.lineage::jsonb IS DISTINCT FROM OLD.lineage::jsonb OR
                    NEW.published_at   IS DISTINCT FROM OLD.published_at
                ) THEN
                    RAISE EXCEPTION 'published snapshots are immutable';
                END IF;
                RETURN NEW;
            END;
            $$;
        SQL);
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Restore the original (broken) definition so rollback is symmetric.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION data_snapshots_published_immutable_guard() RETURNS trigger
            LANGUAGE plpgsql AS $$
            BEGIN
                IF OLD.status = 'published' AND (
                    NEW.run_id         IS DISTINCT FROM OLD.run_id OR
                    NEW.snapshot_uuid  IS DISTINCT FROM OLD.snapshot_uuid OR
                    NEW.version        IS DISTINCT FROM OLD.version OR
                    NEW.status         IS DISTINCT FROM OLD.status OR
                    NEW.window_start   IS DISTINCT FROM OLD.window_start OR
                    NEW.window_end     IS DISTINCT FROM OLD.window_end OR
                    NEW.timezone       IS DISTINCT FROM OLD.timezone OR
                    NEW.lineage        IS DISTINCT FROM OLD.lineage OR
                    NEW.published_at   IS DISTINCT FROM OLD.published_at
                ) THEN
                    RAISE EXCEPTION 'published snapshots are immutable';
                END IF;
                RETURN NEW;
            END;
            $$;
        SQL);
    }
};
