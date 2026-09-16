<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Outlet;
use App\Models\Territory;
use App\Services\PilotQualificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PilotQualificationTest extends TestCase
{
    use RefreshDatabase;

    public function testQualifiesWithSufficientOutlets(): void
    {
        $territory = Territory::create(['name' => 'Pilot Territory', 'code' => 'PILOT-01']);

        for ($i = 0; $i < 15; $i++) {
            $outlet = Outlet::factory()->create([
                'territory_id' => $territory->id,
                'is_active' => true,
            ]);

            Order::create([
                'order_id' => 'ORD-PILOT-QUAL-'.$i,
                'outlet_id' => $outlet->id,
                'status' => 'New',
                'total_amount' => 100000,
                'idempotency_key' => 'pilot-qual-key-'.$i,
            ]);
        }

        $result = (new PilotQualificationService)->evaluate($territory->id);

        $this->assertTrue($result['qualified']);
        $this->assertSame(15, $result['outletCount']);
        $this->assertSame('PASS', $result['outletCheck']);
    }

    public function testFailsWithInsufficientOutlets(): void
    {
        $territory = Territory::create(['name' => 'Small Territory', 'code' => 'SMALL-01']);

        for ($i = 0; $i < 5; $i++) {
            $outlet = Outlet::factory()->create([
                'territory_id' => $territory->id,
                'is_active' => true,
            ]);

            Order::create([
                'order_id' => 'ORD-PILOT-SMALL-'.$i,
                'outlet_id' => $outlet->id,
                'status' => 'New',
                'total_amount' => 100000,
                'idempotency_key' => 'pilot-small-key-'.$i,
            ]);
        }

        $result = (new PilotQualificationService)->evaluate($territory->id);

        $this->assertFalse($result['qualified']);
        $this->assertSame('FAIL', $result['outletCheck']);
    }

    public function testQualifiesWithAllChecksPass(): void
    {
        $territory = Territory::create(['name' => 'All Checks Territory', 'code' => 'ALL-01']);

        for ($i = 0; $i < 15; $i++) {
            $outlet = Outlet::factory()->create([
                'territory_id' => $territory->id,
                'is_active' => true,
            ]);

            Order::create([
                'order_id' => 'ORD-PILOT-ALL-'.$i,
                'outlet_id' => $outlet->id,
                'status' => 'New',
                'total_amount' => 100000,
                'idempotency_key' => 'pilot-all-key-'.$i,
            ]);
        }

        $service = new PilotQualificationService;
        $result = $service->evaluate($territory->id, [
            'hasDocumentation' => true,
            'hasPic' => true,
            'picAvailableStart' => '08:00',
            'picAvailableEnd' => '17:00',
            'internetStable' => true,
        ]);

        $this->assertTrue($result['qualified']);
        $this->assertSame('PASS', $result['documentationCheck']);
        $this->assertSame('PASS', $result['picCheck']);
        $this->assertSame('PASS', $result['internetCheck']);
    }

    public function testFailsDocumentationCheck(): void
    {
        $territory = Territory::create(['name' => 'No Docs Territory', 'code' => 'NODOC-01']);

        for ($i = 0; $i < 15; $i++) {
            $outlet = Outlet::factory()->create([
                'territory_id' => $territory->id,
                'is_active' => true,
            ]);

            Order::create([
                'order_id' => 'ORD-PILOT-NODOC-'.$i,
                'outlet_id' => $outlet->id,
                'status' => 'New',
                'total_amount' => 100000,
                'idempotency_key' => 'pilot-nodoc-key-'.$i,
            ]);
        }

        $result = (new PilotQualificationService)->evaluate($territory->id, [
            'hasDocumentation' => false,
            'hasPic' => true,
            'picAvailableStart' => '08:00',
            'picAvailableEnd' => '17:00',
            'internetStable' => true,
        ]);

        $this->assertFalse($result['qualified']);
        $this->assertSame('FAIL', $result['documentationCheck']);
    }

    public function testFailsPicCheck(): void
    {
        $territory = Territory::create(['name' => 'No PIC Territory', 'code' => 'NOPIC-01']);

        for ($i = 0; $i < 15; $i++) {
            $outlet = Outlet::factory()->create([
                'territory_id' => $territory->id,
                'is_active' => true,
            ]);

            Order::create([
                'order_id' => 'ORD-PILOT-NOPIC-'.$i,
                'outlet_id' => $outlet->id,
                'status' => 'New',
                'total_amount' => 100000,
                'idempotency_key' => 'pilot-nopic-key-'.$i,
            ]);
        }

        $result = (new PilotQualificationService)->evaluate($territory->id, [
            'hasDocumentation' => true,
            'hasPic' => false,
            'internetStable' => true,
        ]);

        $this->assertFalse($result['qualified']);
        $this->assertSame('FAIL', $result['picCheck']);
    }

    public function testFailsInternetCheck(): void
    {
        $territory = Territory::create(['name' => 'No Internet Territory', 'code' => 'NOINET-01']);

        for ($i = 0; $i < 15; $i++) {
            $outlet = Outlet::factory()->create([
                'territory_id' => $territory->id,
                'is_active' => true,
            ]);

            Order::create([
                'order_id' => 'ORD-PILOT-NOINET-'.$i,
                'outlet_id' => $outlet->id,
                'status' => 'New',
                'total_amount' => 100000,
                'idempotency_key' => 'pilot-noinet-key-'.$i,
            ]);
        }

        $result = (new PilotQualificationService)->evaluate($territory->id, [
            'hasDocumentation' => true,
            'hasPic' => true,
            'picAvailableStart' => '08:00',
            'picAvailableEnd' => '17:00',
            'internetStable' => false,
        ]);

        $this->assertFalse($result['qualified']);
        $this->assertSame('FAIL', $result['internetCheck']);
    }
}
