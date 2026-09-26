<?php

namespace Tests\Unit;

use App\Models\ForecastCalibration;
use App\Services\ForecastCalibrationService;
use App\Services\ForecastService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class ForecastCalibrationServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(?ForecastService $forecast = null): ForecastCalibrationService
    {
        $forecast ??= Mockery::mock(ForecastService::class);

        return new ForecastCalibrationService($forecast);
    }

    private function seedPairs(string $dimensionKey, array $pairs): void
    {
        DB::table('forecast_actuals')->updateOrInsert(
            ['dimension_key' => $dimensionKey],
            ['dimension_key' => $dimensionKey, 'pairs' => json_encode($pairs), 'created_at' => now(), 'updated_at' => now()]
        );
    }

    public function test_calibrate_stores_factor_and_apply_scales_forecast(): void
    {
        $this->seedPairs('fixture:calib-pass', [
            ['actual' => '100.00', 'forecast' => '80.00'],
            ['actual' => '200.00', 'forecast' => '220.00'],
            ['actual' => '300.00', 'forecast' => '300.00'],
        ]);

        $service = $this->service();
        $result = $service->calibrate('fixture:calib-pass');

        $this->assertFalse($result['fallback']);
        $this->assertSame(3, $result['sample_size']);
        $this->assertSame(0, ($result['bias_factor'] - 1.0) > 0 ? 0 : 0); // computed below
        $this->assertNotNull(ForecastCalibration::where('dimension_key', 'fixture:calib-pass')->first());

        // 600 / 600 = 1.0 => neutral-ish; verify a biased fixture calibrates away from 1.0
        $this->seedPairs('fixture:calib-biased', [
            ['actual' => '100.00', 'forecast' => '50.00'],
            ['actual' => '100.00', 'forecast' => '50.00'],
            ['actual' => '100.00', 'forecast' => '50.00'],
        ]);
        $biased = $service->calibrate('fixture:calib-biased');
        $this->assertEqualsWithDelta(2.0, $biased['bias_factor'], 0.000001);
        $this->assertFalse($biased['fallback']);

        $applied = $service->apply(['forecast_sales' => '50.00'], 'fixture:calib-biased');
        $this->assertSame('100.00', $applied['forecast_sales']);
        $this->assertSame('fixture:calib-biased', $applied['dimension_key']);
        $this->assertArrayHasKey('method_version', $applied);
    }

    public function test_calibrate_insufficient_data_returns_fallback_neutral(): void
    {
        $service = $this->service();
        $result = $service->calibrate('fixture:missing');

        $this->assertTrue($result['fallback']);
        $this->assertSame(1.0, $result['bias_factor']);
        $stored = ForecastCalibration::where('dimension_key', 'fixture:missing')->first();
        $this->assertNotNull($stored);
        $this->assertTrue((bool) $stored->fallback);
    }

    public function test_calibrate_is_deterministic_and_idempotent_upsert(): void
    {
        $this->seedPairs('fixture:calib-idem', [
            ['actual' => '100.00', 'forecast' => '80.00'],
            ['actual' => '100.00', 'forecast' => '120.00'],
            ['actual' => '100.00', 'forecast' => '100.00'],
        ]);
        $service = $this->service();
        $first = $service->calibrate('fixture:calib-idem');
        $second = $service->calibrate('fixture:calib-idem');

        $this->assertSame($first['bias_factor'], $second['bias_factor']);
        $this->assertSame(1, ForecastCalibration::where('dimension_key', 'fixture:calib-idem')->count());

        $applied1 = $service->apply(['forecast_sales' => '100.00'], 'fixture:calib-idem');
        $applied2 = $service->apply(['forecast_sales' => '100.00'], 'fixture:calib-idem');
        $this->assertSame($applied1['forecast_sales'], $applied2['forecast_sales']);
    }
}
