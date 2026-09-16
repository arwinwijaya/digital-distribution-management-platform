<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Invoice;
use App\Models\OperationalEvent;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Services\OperationalEventService;
use App\Services\PilotMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PilotWorkflowFixtures;
use Tests\TestCase;

class PilotWorkflowTest extends TestCase
{
    use RefreshDatabase;
    use PilotWorkflowFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializePilotWorkflowFixtures();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    // ────────────────────────────────────────────────────────────────────────
    // Step 1–4: Concierge creates order as outlet
    // ────────────────────────────────────────────────────────────────────────
    public function testConciergeCreatesOrderAsOutlet(): void
    {
        $correlationId = 'pilot-create-corr-'.uniqid();
        $response = $this->withToken($this->pilotOutletToken)
            ->postJson('/api/orders', [
                'items' => [['product_id' => $this->pilotProducts[0]->id, 'quantity' => 10]],
                'idempotency_key' => 'pilot-create-001',
            ], ['X-Correlation-ID' => $correlationId]);
        $response->assertCreated();

        $order = Order::findOrFail($response->json('data.id'));

        $this->assertSame('New', $order->status);
        $this->assertSame($this->pilotOutlet->id, $order->outlet_id);
        $this->assertNotNull($order->created_at);

        // Operational event logged by the correlation middleware
        $event = OperationalEvent::where('correlation_id', $correlationId)->first();
        $this->assertNotNull($event, 'OperationalEvent with correlation ID must exist');
        $this->assertSame('success', $event->outcome);
        $this->assertStringContainsString('api/orders', $event->route);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Step 5–8: Order lifecycle to delivered
    // ────────────────────────────────────────────────────────────────────────
    public function testOrderLifecycleToDelivered(): void
    {
        $order = $this->createPilotOrder('pilot-lifecycle-001');
        // Backdate created_at so it is clearly before delivery
        $order->update(['created_at' => now()->subHours(1)]);
        $createdAt = $order->fresh()->created_at->copy();

        $this->approvePilotOrder($order)->assertOk();
        $order->refresh();
        $this->assertSame('Confirmed', $order->status);

        $delivery = $this->startPilotDelivery($order);
        $this->completePilotDelivery($delivery)->assertOk();

        $order->refresh();
        $this->assertSame('Delivered', $order->status);

        $invoice = Invoice::where('order_id', $order->id)->first();
        $this->assertNotNull($invoice);

        $historyStatuses = $order->statusHistory->pluck('status')->values()->all();
        $this->assertContains('New', $historyStatuses);
        $this->assertContains('Confirmed', $historyStatuses);
        $this->assertContains('Delivered', $historyStatuses);

        $deliveredAt = $delivery->fresh()->delivered_at;
        $this->assertNotNull($deliveredAt);
        // deliveredAt must not be before order creation (same second counts)
        $this->assertGreaterThanOrEqual($createdAt->timestamp, $deliveredAt->timestamp);
        // Also verify order status chain already confirms lifecycle succeeded
        $this->assertSame('Delivered', $order->status);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Step 9–12: Cancelled order excluded from valid orders
    // ────────────────────────────────────────────────────────────────────────
    public function testCancelledOrderExcludedFromValidCount(): void
    {
        $order = $this->createPilotOrder('pilot-cancel-001');
        $this->approvePilotOrder($order)->assertOk();
        $this->cancelPilotOrder($order)->assertOk();

        $order->refresh();
        $this->assertSame('Cancelled', $order->status);

        $historyStatuses = $order->statusHistory->pluck('status')->values()->all();
        $this->assertContains('New', $historyStatuses);
        $this->assertContains('Cancelled', $historyStatuses);

        $service = new PilotMetricsService();
        $result = $service->countValidOrders([$order]);
        $this->assertSame(0, $result['validCount']);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Step 13–16: Full lifecycle through payment
    // ────────────────────────────────────────────────────────────────────────
    public function testFullLifecycleCompletesThroughPayment(): void
    {
        $order = $this->createPilotOrder('pilot-full-001');
        $totalAmount = (int) $order->total_amount;

        $this->approvePilotOrder($order)->assertOk();
        $delivery = $this->startPilotDelivery($order);
        $this->completePilotDelivery($delivery)->assertOk();

        $order->refresh();
        $this->assertSame('Delivered', $order->status);

        $this->recordPilotPayment($order, $totalAmount, 'pay-full-001')->assertCreated();

        $order->refresh();
        $this->assertSame('Paid', $order->status);

        $invoice = Invoice::where('order_id', $order->id)->first();
        $this->assertNotNull($invoice);
        $this->assertSame('paid', $invoice->status);

        $payment = \App\Models\Payment::where('order_id', $order->id)->first();
        $this->assertNotNull($payment);

        $historyStatuses = $order->statusHistory->pluck('status')->values()->all();
        $this->assertContains('New', $historyStatuses);
        $this->assertContains('Confirmed', $historyStatuses);
        $this->assertContains('Delivered', $historyStatuses);
        $this->assertContains('Paid', $historyStatuses);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Step 17–20: Cancellation excludes from valid order count
    // ────────────────────────────────────────────────────────────────────────
    public function testCancellationExcludesFromMetricsDenominator(): void
    {
        // Create 3 orders: 2 delivered, 1 cancelled
        $delivered1 = $this->createPilotOrder('pilot-metrics-d1');
        $this->approvePilotOrder($delivered1)->assertOk();
        $d1 = $this->startPilotDelivery($delivered1);
        $this->completePilotDelivery($d1)->assertOk();
        $delivered1->refresh();
        $this->assertSame('Delivered', $delivered1->status);

        $delivered2 = $this->createPilotOrder('pilot-metrics-d2', 1);
        $this->approvePilotOrder($delivered2)->assertOk();
        $d2 = $this->startPilotDelivery($delivered2);
        $this->completePilotDelivery($d2)->assertOk();
        $delivered2->refresh();
        $this->assertSame('Delivered', $delivered2->status);

        $cancelled = $this->createPilotOrder('pilot-metrics-c1', 2);
        $this->approvePilotOrder($cancelled)->assertOk();
        $this->cancelPilotOrder($cancelled)->assertOk();
        $cancelled->refresh();
        $this->assertSame('Cancelled', $cancelled->status);

        $service = new PilotMetricsService();
        $result = $service->countValidOrders([$delivered1, $delivered2, $cancelled]);

        $this->assertSame(2, $result['validCount']);
        $this->assertSame(1, $result['cancelledCount']);
        $this->assertSame(3, $result['count']);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Step 21–24: Correlation ID propagates across lifecycle
    // ────────────────────────────────────────────────────────────────────────
    public function testCorrelationIdPropagatesAcrossLifecycle(): void
    {
        $correlationId = 'pilot-corr-'.uniqid();
        $order = $this->withToken($this->pilotOutletToken)
            ->postJson('/api/orders', [
                'items' => [['product_id' => $this->pilotProducts[0]->id, 'quantity' => 10]],
                'idempotency_key' => $correlationId,
            ], ['X-Correlation-ID' => $correlationId])
            ->assertCreated()
            ->json('data');

        $orderId = $order['id'];
        $orderModel = Order::findOrFail($orderId);

        // Approve
        $this->withToken($this->pilotAdminToken)
            ->putJson("/api/orders/{$orderId}/approve", [], ['X-Correlation-ID' => $correlationId])
            ->assertOk();

        // Deliver
        $delivery = $this->startPilotDelivery($orderModel);
        $this->completePilotDelivery($delivery)->assertOk();

        // Pay
        $orderModel->refresh();
        $this->recordPilotPayment($orderModel, (int) $orderModel->total_amount, 'corr-pay-1')->assertCreated();

        $events = OperationalEvent::where('correlation_id', $correlationId)->get();
        $this->assertGreaterThanOrEqual(1, $events->count(), 'At least one event with correlation ID must exist');
        $eventActions = $events->pluck('action')->filter()->values()->all();
        $this->assertNotEmpty($eventActions);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Step 25–28: Volume below target triggers extend
    // ────────────────────────────────────────────────────────────────────────
    public function testVolumeBelowTargetTriggersExtend(): void
    {
        $service = new PilotMetricsService();
        $result = $service->evaluateVolume(14, 7);

        $this->assertFalse($result['targetMet']);
        $this->assertSame(14, $result['validOrderCount']);
        $this->assertSame(3, $result['extendDays']);
        $this->assertSame(10, $result['newDeadlineDay']);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Step 29–32: Volume meets target
    // ────────────────────────────────────────────────────────────────────────
    public function testVolumeMeetsTarget(): void
    {
        $service = new PilotMetricsService();
        $result = $service->evaluateVolume(22, 7);

        $this->assertTrue($result['targetMet']);
        $this->assertSame(22, $result['validOrderCount']);
        $this->assertSame(0, $result['extendDays']);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Step 33–36: Speed delta calculation
    // ────────────────────────────────────────────────────────────────────────
    public function testSpeedDeltaCalculation(): void
    {
        $service = new PilotMetricsService();
        $result = $service->calculateSpeedDelta(48.0, 4.0);

        $this->assertEqualsWithDelta(-91.7, $result['deltaPercent'], 0.2);
        $this->assertTrue($result['targetMet']);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Step 37–40: Reliability guardrails
    // ────────────────────────────────────────────────────────────────────────
    public function testReliabilityGuardrails(): void
    {
        $service = new PilotMetricsService();
        $result = $service->calculateReliability([
            'total' => 25,
            'errors' => 1,
            'delivered' => 24,
            'paid' => 23,
        ]);

        $this->assertEqualsWithDelta(4.0, $result['errorRate'], 0.1);
        $this->assertEqualsWithDelta(96.0, $result['deliverySuccess'], 0.1);
        $this->assertEqualsWithDelta(95.8, $result['paymentCompletion'], 0.1);
        $this->assertTrue($result['guardrailsPassed']);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Step 41–44: Guardrail violation detected
    // ────────────────────────────────────────────────────────────────────────
    public function testGuardrailViolationDetected(): void
    {
        $service = new PilotMetricsService();
        $result = $service->calculateReliability([
            'total' => 25,
            'errors' => 2,
            'delivered' => 25,
            'paid' => 20,
        ]);

        $this->assertEqualsWithDelta(8.0, $result['errorRate'], 0.1);
        $this->assertFalse($result['guardrailsPassed']);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Step 45–50: OperationalEventService pilot query helpers
    // ────────────────────────────────────────────────────────────────────────
    public function testOperationalEventPilotQuery(): void
    {
        $correlationId = 'pilot-query-'.uniqid();
        $events = app(OperationalEventService::class);

        $events->record($correlationId, 'POST /api/orders', 'OrderController@store', 1, 201, null, null, [
            'event_type' => 'order.created',
        ]);

        $events->record($correlationId, 'PUT /api/orders/1/approve', 'OrderController@approve', 1, 200);

        $result = $events->getEventsByCorrelation($correlationId);
        $this->assertCount(2, $result);

        $eventTypes = $result->pluck('metadata.event_type')->filter()->values()->all();
        $this->assertContains('order.created', $eventTypes);
    }

    public function testGetPilotEvents(): void
    {
        $pilotId = 'pilot-2026-09-15';
        $events = app(OperationalEventService::class);

        $events->record('corr-a', 'POST /api/orders', 'OrderController@store', 1, 201, null, null, [
            'pilot_id' => $pilotId,
            'event_type' => 'order.created',
        ]);
        $events->record('corr-a', 'PUT /api/orders/1/approve', 'OrderController@approve', 1, 200, null, null, [
            'pilot_id' => $pilotId,
            'event_type' => 'order.approved',
        ]);

        // Event without pilot_id
        $events->record('corr-b', 'POST /api/orders', 'OrderController@store', 2, 201);

        $pilotEvents = $events->getPilotEvents($pilotId);
        $this->assertCount(2, $pilotEvents);

        $eventTypes = $pilotEvents->pluck('metadata.event_type')->filter()->values()->all();
        $this->assertContains('order.created', $eventTypes);
        $this->assertContains('order.approved', $eventTypes);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Step 51–54: DB-measured lifecycle timing feeds speed delta
    // ────────────────────────────────────────────────────────────────────────
    public function testDbTimingFeedsIntoSpeedDelta(): void
    {
        $service = new PilotMetricsService();

        // Create order normally, then backdate created_at via raw update
        $order = Order::create([
            'order_id' => 'ORD-TIMING-'.uniqid(),
            'outlet_id' => $this->pilotOutlet->id,
            'status' => 'Delivered',
            'total_amount' => 100000,
            'paid_amount' => 0,
            'idempotency_key' => 'timing-test-'.uniqid(),
        ]);
        // Backdate created_at 4 hours via raw DB to bypass model timestamps
        \Illuminate\Support\Facades\DB::table('orders')
            ->where('id', $order->id)
            ->update(['created_at' => now()->subHours(4)]);
        $createdAt = now()->subHours(4);

        // Create delivery with delivered_at set to now (4 hours after created_at)
        Delivery::create([
            'order_id' => $order->id,
            'driver_id' => $this->pilotDriver->id,
            'assigned_by_id' => $this->pilotAdmin->id,
            'status' => 'delivered',
            'delivered_at' => now()->copy(),
            'recipient_name' => 'Timing Test',
        ]);

        $timing = $service->measureLifecycleTiming($order->id);
        // Platform duration from DB: created_at -> delivered_at must be ~4h
        $this->assertGreaterThan(0.0, $timing['platformHours'], 'DB-measured platform hours must be positive');
        $this->assertEqualsWithDelta(4.0, $timing['platformHours'], 0.2);

        $delta = $service->calculateSpeedDelta(48.0, $timing['platformHours']);
        $this->assertEqualsWithDelta(-91.7, $delta['deltaPercent'], 0.5);
        $this->assertTrue($delta['targetMet']);
    }
}
