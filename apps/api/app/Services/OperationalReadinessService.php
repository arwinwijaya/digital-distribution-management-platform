<?php

namespace App\Services;

use App\Models\DataPipelineRun;
use App\Services\DataPipelineService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class OperationalReadinessService
{
    public function __construct(
        private readonly PrePilotFeatureGate $gate,
    ) {
    }

    public function readiness(Request $request): array
    {
        $checks = [
            $this->databaseCheck(),
            $this->prePilotFlagCheck(),
            $this->schedulerCheck(),
            $this->whatsappCheck(),
            $this->pipelineCheck(),
        ];

        $statuses = collect($checks)->pluck('status')->unique()->values()->all();
        $status = in_array('blocked', $statuses, true) ? 'blocked'
            : (in_array('warning', $statuses, true) ? 'warning' : 'ready');

        $correlationId = (string) ($request->attributes->get('correlation_id') ?? $request->headers->get('X-Correlation-ID') ?? (string) Str::uuid());

        return [
            'status' => $status,
            'checks' => $checks,
            'evaluated_at' => now()->toIso8601String(),
            'correlation_id' => $correlationId,
        ];
    }

    private function databaseCheck(): array
    {
        try {
            DB::connection()->getPdo();
            $driver = config('database.default');

            return [
                'name' => 'database_connectivity',
                'status' => 'ready',
                'evidence' => 'database connection ok ('.$driver.')',
                'remediation' => null,
            ];
        } catch (Throwable $e) {
            return [
                'name' => 'database_connectivity',
                'status' => 'blocked',
                'evidence' => 'database connection failed: '.get_class($e),
                'remediation' => 'verify DB connection configuration and database availability',
            ];
        }
    }

    private function prePilotFlagCheck(): array
    {
        $enabled = $this->gate->isEnabled();
        $configEnabled = (bool) config('pre_pilot.enabled');
        $killSwitch = (bool) config('pre_pilot.kill_switch');

        if ($enabled) {
            return [
                'name' => 'pre_pilot_flag',
                'status' => 'ready',
                'evidence' => 'pre-pilot enabled and kill switch off',
                'remediation' => null,
            ];
        }

        return [
            'name' => 'pre_pilot_flag',
            'status' => 'blocked',
            'evidence' => 'pre-pilot enabled='.($configEnabled ? 'true' : 'false').', kill_switch='.($killSwitch ? 'true' : 'false'),
            'remediation' => 'set PRE_PILOT_ENABLED=true and PRE_PILOT_KILL_SWITCH=false to enable pre-pilot operations',
        ];
    }

    private function schedulerCheck(): array
    {
        $kernel = file_get_contents(app_path('Console/Kernel.php')) ?: '';
        $timezone = config('app.timezone');
        $hasJakarta = str_contains($kernel, 'Asia/Jakarta');
        $hasOverlap = str_contains($kernel, 'withoutOverlapping');

        if ($hasJakarta && $hasOverlap) {
            return [
                'name' => 'scheduler_convention',
                'status' => 'ready',
                'evidence' => 'scheduler uses Asia/Jakarta with withoutOverlapping (app.timezone='.$timezone.')',
                'remediation' => null,
            ];
        }

        return [
            'name' => 'scheduler_convention',
            'status' => 'warning',
            'evidence' => 'scheduler convention mismatch: timezone/overlap not confirmed',
            'remediation' => 'ensure scheduled commands use Asia/Jakarta timezone with withoutOverlapping',
        ];
    }

    private function whatsappCheck(): array
    {
        $enabled = (bool) config('whatsapp.enabled');

        if (! $enabled) {
            return [
                'name' => 'whatsapp_config',
                'status' => 'blocked',
                'evidence' => 'whatsapp.enabled=false',
                'remediation' => 'set WHATSAPP_ENABLED=true or confirm outage handling',
            ];
        }

        $missing = [];
        foreach (['access_token', 'phone_number_id'] as $key) {
            if (empty(config('whatsapp.'.$key))) {
                $missing[] = 'whatsapp.'.$key;
            }
        }

        if ($missing !== []) {
            return [
                'name' => 'whatsapp_config',
                'status' => 'warning',
                'evidence' => 'whatsapp enabled but missing '.implode(',', $missing),
                'remediation' => 'provide WhatsApp credentials: '.implode(',', $missing),
            ];
        }

        return [
            'name' => 'whatsapp_config',
            'status' => 'ready',
            'evidence' => 'whatsapp enabled with required configuration',
            'remediation' => null,
        ];
    }

    private function pipelineCheck(): array
    {
        try {
            $latest = DataPipelineRun::query()->orderByDesc('id')->first();

            if ($latest === null) {
                return [
                    'name' => 'data_pipeline',
                    'status' => 'warning',
                    'evidence' => 'no data pipeline runs recorded',
                    'remediation' => 'run data:pipeline at least once before pilot',
                ];
            }

            if ($latest->status === 'completed') {
                return [
                    'name' => 'data_pipeline',
                    'status' => 'ready',
                    'evidence' => 'latest run '.$latest->run_uuid.' completed ('.DataPipelineService::TIMEZONE.')',
                    'remediation' => null,
                ];
            }

            if (in_array($latest->status, ['running', 'processing', 'active'], true)) {
                return [
                    'name' => 'data_pipeline',
                    'status' => 'warning',
                    'evidence' => 'latest run '.$latest->run_uuid.' is '.$latest->status,
                    'remediation' => 'wait for the active pipeline run to finish',
                ];
            }

            return [
                'name' => 'data_pipeline',
                'status' => 'blocked',
                'evidence' => 'latest run '.$latest->run_uuid.' status '.$latest->status,
                'remediation' => 'inspect the failed pipeline run and rerun data:pipeline',
            ];
        } catch (Throwable $e) {
            return [
                'name' => 'data_pipeline',
                'status' => 'blocked',
                'evidence' => 'pipeline status unavailable: '.get_class($e),
                'remediation' => 'verify pipeline tables/migrations and rerun',
            ];
        }
    }
}
