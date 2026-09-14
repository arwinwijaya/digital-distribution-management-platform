<?php

namespace App\Console\Commands;

use App\Services\DataPipelineService;
use Illuminate\Console\Command;

/**
 * Daily scheduled pipeline command (02:00 Asia/Jakarta).
 */
class RunDataPipeline extends Command
{
    protected $signature = 'data:pipeline';

    protected $description = 'Run the daily data-intelligence pipeline for the prior-day and rolling operational windows';

    public function handle(DataPipelineService $service): int
    {
        if ($service->hasActiveRun()) {
            $this->line('Pipeline run is skipped: another run is already active.');

            return self::SUCCESS;
        }

        $service->run();

        $this->line('Pipeline run completed.');

        return self::SUCCESS;
    }
}
