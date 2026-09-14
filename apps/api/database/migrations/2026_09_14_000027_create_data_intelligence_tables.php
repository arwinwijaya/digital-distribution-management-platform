<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_pipeline_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('run_uuid')->unique();
            $table->string('status', 32);
            $table->string('pipeline_version', 64);
            $table->date('window_start');
            $table->date('window_end');
            $table->string('timezone', 64)->default('Asia/Jakarta');
            $table->json('lineage')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at'], 'data_pipeline_runs_status_created_idx');
        });

        Schema::create('data_metric_definitions', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 128)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('method_version', 64);
            $table->json('definition')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('data_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('run_id')->constrained('data_pipeline_runs')->restrictOnDelete();
            $table->uuid('snapshot_uuid')->unique();
            $table->unsignedInteger('version');
            $table->string('status', 32);
            $table->boolean('is_active')->default(false);
            $table->date('window_start');
            $table->date('window_end');
            $table->string('timezone', 64)->default('Asia/Jakarta');
            $table->json('lineage')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->timestamps();
            $table->unique(['run_id', 'version'], 'data_snapshots_run_version_unique');
            $table->index(['status', 'is_active'], 'data_snapshots_status_active_idx');
        });

        Schema::create('data_snapshot_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('snapshot_id')->constrained('data_snapshots')->restrictOnDelete();
            $table->foreignId('metric_definition_id')->constrained('data_metric_definitions')->restrictOnDelete();
            $table->string('section', 64);
            $table->string('dimension_key', 255)->nullable();
            $table->json('dimension')->nullable();
            $table->json('value');
            $table->timestamps();
            $table->unique(['snapshot_id', 'section', 'dimension_key'], 'data_snapshot_values_identity_unique');
            $table->index(['snapshot_id', 'section'], 'data_snapshot_values_section_idx');
        });

        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['sqlite', 'pgsql'], true)) {
            DB::statement("CREATE UNIQUE INDEX data_pipeline_runs_one_active_idx ON data_pipeline_runs ((1)) WHERE status IN ('running', 'processing', 'active')");
            DB::statement("CREATE UNIQUE INDEX data_snapshots_one_active_publication_idx ON data_snapshots ((1)) WHERE is_active = true");
        }

        $this->createImmutabilityGuards($driver);
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        $this->dropImmutabilityGuards($driver);

        if (in_array($driver, ['sqlite', 'pgsql'], true)) {
            DB::statement('DROP INDEX IF EXISTS data_pipeline_runs_one_active_idx');
            DB::statement('DROP INDEX IF EXISTS data_snapshots_one_active_publication_idx');
        }

        Schema::dropIfExists('data_snapshot_values');
        Schema::dropIfExists('data_snapshots');
        Schema::dropIfExists('data_metric_definitions');
        Schema::dropIfExists('data_pipeline_runs');
    }

    private function createImmutabilityGuards(string $driver): void
    {
        if ($driver === 'sqlite') {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER data_snapshots_published_immutable_update
                BEFORE UPDATE ON data_snapshots
                WHEN OLD.status = 'published' AND (
                    NEW.run_id IS NOT OLD.run_id OR
                    NEW.snapshot_uuid IS NOT OLD.snapshot_uuid OR
                    NEW.version IS NOT OLD.version OR
                    NEW.status IS NOT OLD.status OR
                    NEW.window_start IS NOT OLD.window_start OR
                    NEW.window_end IS NOT OLD.window_end OR
                    NEW.timezone IS NOT OLD.timezone OR
                    NEW.lineage IS NOT OLD.lineage OR
                    NEW.published_at IS NOT OLD.published_at
                )
                BEGIN
                    SELECT RAISE(ABORT, 'published snapshots are immutable');
                END
            SQL);
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER data_snapshots_published_immutable_delete
                BEFORE DELETE ON data_snapshots
                WHEN OLD.status = 'published'
                BEGIN
                    SELECT RAISE(ABORT, 'published snapshots are immutable');
                END
            SQL);
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER data_snapshot_values_published_immutable_update
                BEFORE UPDATE ON data_snapshot_values
                WHEN EXISTS (SELECT 1 FROM data_snapshots WHERE id = OLD.snapshot_id AND status = 'published')
                BEGIN
                    SELECT RAISE(ABORT, 'published snapshot values are immutable');
                END
            SQL);
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER data_snapshot_values_published_immutable_delete
                BEFORE DELETE ON data_snapshot_values
                WHEN EXISTS (SELECT 1 FROM data_snapshots WHERE id = OLD.snapshot_id AND status = 'published')
                BEGIN
                    SELECT RAISE(ABORT, 'published snapshot values are immutable');
                END
            SQL);
            return;
        }

        if ($driver === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE FUNCTION data_snapshots_published_immutable_guard() RETURNS trigger
                LANGUAGE plpgsql AS $$
                BEGIN
                    IF OLD.status = 'published' AND (
                        NEW.run_id IS DISTINCT FROM OLD.run_id OR
                        NEW.snapshot_uuid IS DISTINCT FROM OLD.snapshot_uuid OR
                        NEW.version IS DISTINCT FROM OLD.version OR
                        NEW.status IS DISTINCT FROM OLD.status OR
                        NEW.window_start IS DISTINCT FROM OLD.window_start OR
                        NEW.window_end IS DISTINCT FROM OLD.window_end OR
                        NEW.timezone IS DISTINCT FROM OLD.timezone OR
                        NEW.lineage IS DISTINCT FROM OLD.lineage OR
                        NEW.published_at IS DISTINCT FROM OLD.published_at
                    ) THEN
                        RAISE EXCEPTION 'published snapshots are immutable';
                    END IF;
                    RETURN NEW;
                END;
                $$;
                CREATE TRIGGER data_snapshots_published_immutable_update
                BEFORE UPDATE ON data_snapshots
                FOR EACH ROW EXECUTE FUNCTION data_snapshots_published_immutable_guard();

                CREATE FUNCTION data_snapshots_published_immutable_delete_guard() RETURNS trigger
                LANGUAGE plpgsql AS $$
                BEGIN
                    IF OLD.status = 'published' THEN
                        RAISE EXCEPTION 'published snapshots are immutable';
                    END IF;
                    RETURN OLD;
                END;
                $$;
                CREATE TRIGGER data_snapshots_published_immutable_delete
                BEFORE DELETE ON data_snapshots
                FOR EACH ROW EXECUTE FUNCTION data_snapshots_published_immutable_delete_guard();

                CREATE FUNCTION data_snapshot_values_published_immutable_guard() RETURNS trigger
                LANGUAGE plpgsql AS $$
                BEGIN
                    IF EXISTS (SELECT 1 FROM data_snapshots WHERE id = OLD.snapshot_id AND status = 'published') THEN
                        RAISE EXCEPTION 'published snapshot values are immutable';
                    END IF;
                    RETURN NEW;
                END;
                $$;
                CREATE TRIGGER data_snapshot_values_published_immutable_update
                BEFORE UPDATE ON data_snapshot_values
                FOR EACH ROW EXECUTE FUNCTION data_snapshot_values_published_immutable_guard();
                CREATE TRIGGER data_snapshot_values_published_immutable_delete
                BEFORE DELETE ON data_snapshot_values
                FOR EACH ROW EXECUTE FUNCTION data_snapshot_values_published_immutable_guard();
            SQL);
        }
    }

    private function dropImmutabilityGuards(string $driver): void
    {
        if ($driver === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS data_snapshot_values_published_immutable_delete');
            DB::unprepared('DROP TRIGGER IF EXISTS data_snapshot_values_published_immutable_update');
            DB::unprepared('DROP TRIGGER IF EXISTS data_snapshots_published_immutable_delete');
            DB::unprepared('DROP TRIGGER IF EXISTS data_snapshots_published_immutable_update');
        } elseif ($driver === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS data_snapshot_values_published_immutable_delete ON data_snapshot_values');
            DB::unprepared('DROP TRIGGER IF EXISTS data_snapshot_values_published_immutable_update ON data_snapshot_values');
            DB::unprepared('DROP TRIGGER IF EXISTS data_snapshots_published_immutable_delete ON data_snapshots');
            DB::unprepared('DROP TRIGGER IF EXISTS data_snapshots_published_immutable_update ON data_snapshots');
            DB::unprepared('DROP FUNCTION IF EXISTS data_snapshot_values_published_immutable_guard()');
            DB::unprepared('DROP FUNCTION IF EXISTS data_snapshots_published_immutable_delete_guard()');
            DB::unprepared('DROP FUNCTION IF EXISTS data_snapshots_published_immutable_guard()');
        }
    }
};
