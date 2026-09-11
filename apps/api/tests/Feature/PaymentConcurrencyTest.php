<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\PaymentConcurrencyHarness;
use Tests\TestCase;

class PaymentConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected Outlet $outlet;
    protected ?PaymentConcurrencyHarness $raceHarness = null;

    protected function setUp(): void
    {
        parent::setUp();

        $outletUser = User::factory()->outlet()->create([
            'email' => 'payment-concurrency-outlet@ddp.test',
            'password' => Hash::make('password123'),
        ]);
        $this->outlet = Outlet::factory()->create([
            'user_id' => $outletUser->id,
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        $this->raceHarness?->close();
        parent::tearDown();
    }

    /**
     * Two independent public HTTP workers submit one payment identity at the
     * same time. Both responses must replay the committed payment rather than
     * exposing the unique-key race as a server error.
     */
    public function test_concurrent_same_identity_payment_posts_replay_one_payment(): void
    {
        $this->raceHarness = new PaymentConcurrencyHarness();
        $this->raceHarness->prepare();
        $order = $this->raceHarness->createPaymentFixture();
        $this->raceHarness->startServers();

        $responses = $this->raceHarness->runConcurrentPayment($order->id, [
            'amount' => 40000,
            'payment_method' => 'cash',
            'idempotency_key' => 'concurrent-payment-key',
        ]);
        $statuses = array_column($responses, 'status');
        sort($statuses);

        $this->assertSame([200, 201], $statuses);
        $this->assertSame(['success', 'success'], array_column(array_column($responses, 'json'), 'status'));
        $this->assertSame($responses[0]['json']['data']['id'], $responses[1]['json']['data']['id']);
        $this->assertSame(1, Payment::on(PaymentConcurrencyHarness::CONNECTION)->count());
        $payment = Payment::on(PaymentConcurrencyHarness::CONNECTION)->sole();
        $this->assertSame('completed', $payment->status);
        $this->assertSame('40000.00', (string) $payment->amount);
        $this->assertSame('40000.00', (string) Order::on(PaymentConcurrencyHarness::CONNECTION)->findOrFail($order->id)->paid_amount);
        $this->assertSame('Partially Paid', Order::on(PaymentConcurrencyHarness::CONNECTION)->findOrFail($order->id)->status);
        $this->assertSame(1, Invoice::on(PaymentConcurrencyHarness::CONNECTION)->where('order_id', $order->id)->count());
        $this->assertSame('40000.00', (string) Invoice::on(PaymentConcurrencyHarness::CONNECTION)->where('order_id', $order->id)->value('paid_amount'));
        $this->assertSame('60000.00', (string) Invoice::on(PaymentConcurrencyHarness::CONNECTION)->where('order_id', $order->id)->value('balance_amount'));
        $this->assertSame(Invoice::PARTIALLY_PAID, Invoice::on(PaymentConcurrencyHarness::CONNECTION)->where('order_id', $order->id)->value('status'));
    }
}
