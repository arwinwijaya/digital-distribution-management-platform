<?php

namespace Tests\Feature\Concurrency;

use App\Models\Invoice;
use App\Models\OrderStatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InvoiceConcurrencyHarness;
use Tests\Support\PostgresRaceProbe;
use Tests\TestCase;

/**
 * Approval/invoice concurrency coverage extracted from InvoiceTest so race
 * scenarios live with the rest of the Concurrency category.
 *
 * Requires a reachable PostgreSQL database; skips gracefully (via
 * PostgresRaceProbe) when the Docker-only `db` hostname cannot be resolved.
 */
class InvoiceConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected ?InvoiceConcurrencyHarness $raceHarness = null;

    protected function tearDown(): void
    {
        $this->raceHarness?->close();
        parent::tearDown();
    }

    public function test_concurrent_approvals_create_one_invoice(): void
    {
        if (! PostgresRaceProbe::isAvailable()) {
            $this->markTestSkipped('PostgreSQL race database is unreachable; skipping approval concurrency coverage.');
        }

        $this->raceHarness = new InvoiceConcurrencyHarness();
        $this->raceHarness->prepare();
        $order = $this->raceHarness->createOrderFixture();
        $this->raceHarness->startServers();

        $responses = $this->raceHarness->runConcurrentApprovals($order->id);
        $this->assertSame([200, 200], array_values(array_column($responses, 'status')));
        $this->assertSame(1, Invoice::on(InvoiceConcurrencyHarness::CONNECTION)->where('order_id', $order->id)->count());
        $this->assertSame(1, OrderStatusHistory::on(InvoiceConcurrencyHarness::CONNECTION)->where('order_id', $order->id)->where('status', 'Confirmed')->count());
    }
}
