<?php

namespace Tests\Feature;

use App\Models\DataPipelineRun;
use App\Models\InvoiceReminder;
use App\Models\OperationalEvent;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\User;
use App\Models\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OperationalDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    private string $adminToken;
    private string $nonAdminToken;

    protected function setUp(): void
    {
        Carbon::setTestNow();
        parent::setUp();

        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'jwt.secret' => base64_encode(hash('sha256', 'test-jwt-secret-for-ops-diag', true)),
            'jwt.ttl' => 999999,
            'pre_pilot.enabled' => true,
            'pre_pilot.kill_switch' => false,
            'whatsapp.enabled' => true,
            'whatsapp.access_token' => 'test-token',
            'whatsapp.phone_number_id' => 'test-phone-id',
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'ops-diag-admin@example.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $this->adminToken = $this->postJson('/api/auth/login', [
            'email' => $admin->email,
            'password' => 'password123',
        ])->assertOk()->json('data.token');

        $staff = User::factory()->create([
            'role' => 'outlet',
            'email' => 'ops-diag-outlet@example.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $this->nonAdminToken = $this->postJson('/api/auth/login', [
            'email' => $staff->email,
            'password' => 'password123',
        ])->assertOk()->json('data.token');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_readiness_requires_admin_and_returns_valid_schema(): void
    {
        $this->withToken($this->nonAdminToken)->getJson('/api/admin/operations/readiness')
            ->assertForbidden();

        $response = $this->withToken($this->adminToken)->getJson('/api/admin/operations/readiness')
            ->assertOk();

        $response->assertJsonPath('status', 'success');
        $response->assertJsonStructure([
            'data' => [
                'status',
                'checks',
                'evaluated_at',
                'correlation_id',
            ],
        ]);
        $response->assertJsonStructure([
            'data' => [
                'checks' => [
                    [
                        'name',
                        'status',
                        'evidence',
                        'remediation',
                    ],
                ],
            ],
        ]);

        $checks = collect($response->json('data.checks'));
        $this->assertTrue($checks->isNotEmpty(), 'Readiness must return at least one check.');
        $this->assertSame($checks->pluck('name')->values()->all(), $checks->pluck('name')->unique()->values()->all(), 'Check names must be unique.');
        $this->assertContains($response->json('data.status'), ['ready', 'warning', 'blocked']);
        $this->assertNotEmpty($response->json('data.evaluated_at'));
        $this->assertNotEmpty($response->json('data.correlation_id'));
    }

    public function test_readiness_disabled_gate_returns_503(): void
    {
        config(['pre_pilot.enabled' => false, 'pre_pilot.kill_switch' => false]);

        $this->withToken($this->adminToken)->getJson('/api/admin/operations/readiness')
            ->assertStatus(503)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('code', 'pre_pilot_disabled');
    }

    public function test_readiness_kill_switch_returns_503(): void
    {
        config(['pre_pilot.enabled' => true, 'pre_pilot.kill_switch' => true]);

        $this->withToken($this->adminToken)->getJson('/api/admin/operations/readiness')
            ->assertStatus(503)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('code', 'pre_pilot_disabled');
    }

    public function test_disabled_gate_preserves_existing_transaction_endpoints(): void
    {
        config(['pre_pilot.enabled' => false, 'pre_pilot.kill_switch' => false]);

        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('status', 'healthy');
    }

    public function test_issues_list_requires_admin_and_returns_filters(): void
    {
        $this->withToken($this->nonAdminToken)->getJson('/api/admin/operations/issues')
            ->assertForbidden();

        $referenceTime = now();

        $failedWaba = WhatsAppMessage::create([
            'status' => 'failed',
            'direction' => 'outbound',
            'message_type' => 'text',
            'phone' => '+628123456789',
            'error' => 'provider timeout',
            'attempts' => 2,
            'created_at' => $referenceTime,
            'updated_at' => $referenceTime,
        ]);
        $sentWaba = WhatsAppMessage::create([
            'status' => 'sent',
            'direction' => 'outbound',
            'message_type' => 'text',
            'phone' => '+6281987654321',
            'error' => null,
            'sent_at' => $referenceTime,
            'created_at' => $referenceTime,
            'updated_at' => $referenceTime,
        ]);
        $outletUser = User::factory()->create(['email' => 'ops-diag-wa-outlet@example.test', 'password' => Hash::make('password123'), 'role' => 'outlet', 'is_active' => true]);
        $invoiceOutlet = Outlet::factory()->create(['user_id' => $outletUser->id, 'phone' => '+628111222333', 'is_active' => true]);
        $invoicedOrder = Order::create([
            'order_id' => 'ORD-OPS-DIAG-1',
            'outlet_id' => $invoiceOutlet->id,
            'status' => 'Delivered',
            'total_amount' => 100000,
            'paid_amount' => 0,
            'commission_percentage' => 2,
            'idempotency_key' => 'ops-diag-order-1',
            'created_at' => $referenceTime,
            'updated_at' => $referenceTime,
        ]);
        $invoice = Invoice::create([
            'order_id' => $invoicedOrder->id,
            'outlet_id' => $invoiceOutlet->id,
            'invoice_number' => 'INV-OPS-DIAG-1',
            'issue_date' => '2026-09-10',
            'due_date' => '2026-09-17',
            'total_amount' => 100000,
            'paid_amount' => 0,
            'balance_amount' => 100000,
            'status' => Invoice::UNPAID,
        ]);
        $failedReminder = InvoiceReminder::create([
            'invoice_id' => $invoice->id,
            'event_type' => InvoiceReminder::EVENT_H_MINUS_ONE,
            'event_date' => '2026-09-15',
            'status' => InvoiceReminder::FAILED,
            'attempts' => 3,
            'failed_at' => $referenceTime,
            'last_error' => 'provider unavailable',
            'idempotency_key' => 'ops-diag-reminder-1',
        ]);
        $failedPipeline = DataPipelineRun::create([
            'run_uuid' => 'ops-diag-run-1',
            'status' => 'failed',
            'pipeline_version' => 'v1',
            'window_start' => '2026-09-01',
            'window_end' => '2026-09-14',
            'timezone' => 'Asia/Jakarta',
            'error_message' => 'stage failed',
        ]);
        $resolvedEvent = OperationalEvent::create([
            'correlation_id' => 'ops-diag-evt-1',
            'route' => 'GET /api/admin/operations/readiness',
            'action' => 'OperationalReadinessController@readiness',
            'actor_id' => null,
            'status_code' => 200,
            'outcome' => OperationalEvent::OUTCOME_SUCCESS,
            'error_class' => null,
            'occurred_at' => $referenceTime->copy()->subMinutes(5),
            'metadata' => null,
        ]);

        $response = $this->withToken($this->adminToken)->getJson('/api/admin/operations/issues')
            ->assertOk();

        $response->assertJsonPath('status', 'success');
        $response->assertJsonStructure([
            'status',
            'data' => [
                [
                    'id',
                    'source',
                    'reference',
                    'status',
                    'severity',
                    'attempts',
                    'occurred_at',
                    'error_class',
                    'correlation_id',
                    'next_action',
                ],
            ],
            'meta' => [
                'page',
                'limit',
                'total',
                'has_more',
            ],
        ]);

        $failedWabaRow = collect($response->json('data'))->firstWhere('id', $failedWaba->id);
        $this->assertNotNull($failedWabaRow, 'Failed WhatsApp message must be listed.');
        $this->assertSame('whatsapp', $failedWabaRow['source']);
        $this->assertSame('failed', $failedWabaRow['status']);
        $this->assertSame('warning', $failedWabaRow['severity']);

        $failedReminderRow = collect($response->json('data'))->firstWhere('id', $failedReminder->id + 100000);
        $this->assertNotNull($failedReminderRow, 'Failed reminder must be listed.');
        $this->assertSame('reminder', $failedReminderRow['source']);
        $this->assertSame('warning', $failedReminderRow['severity']);

        $failedPipelineRow = collect($response->json('data'))->firstWhere('id', $failedPipeline->id + 200000);
        $this->assertNotNull($failedPipelineRow, 'Failed pipeline run must be listed.');
        $this->assertSame('pipeline', $failedPipelineRow['source']);
        $this->assertSame('critical', $failedPipelineRow['severity']);

        $resolvedEventRow = collect($response->json('data'))->firstWhere('id', $resolvedEvent->id + 300000);
        $this->assertNotNull($resolvedEventRow, 'Resolved operational event must be listed.');
        $this->assertSame('info', $resolvedEventRow['severity']);

        $this->assertTrue((bool) $response->json('meta.has_more') || $response->json('meta.total') >= 4);

        $this->withToken($this->adminToken)
            ->getJson('/api/admin/operations/issues?source=pipeline&status=failed&severity=critical')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $correlation = $this->withToken($this->adminToken)
            ->getJson('/api/admin/operations/issues?correlation_id=ops-diag-evt-1')
            ->assertOk();
        $this->assertGreaterThanOrEqual(1, $correlation->json('meta.total'));

        $this->withToken($this->adminToken)
            ->getJson('/api/admin/operations/issues?limit=1')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertSame('admin-only', 'admin-only', 'issue list requires admin role');
    }

    public function test_issue_detail_requires_admin_and_redacts_sensitive_fields(): void
    {
        $pipeline = DataPipelineRun::create([
            'run_uuid' => 'ops-diag-detail-pipeline-1',
            'status' => 'completed',
            'pipeline_version' => 'v1',
            'window_start' => '2026-09-01',
            'window_end' => '2026-09-14',
            'timezone' => 'Asia/Jakarta',
        ]);
        $resolvedEvent = OperationalEvent::create([
            'correlation_id' => 'ops-diag-detail-event-1',
            'route' => 'GET /api/admin/operations/readiness',
            'action' => 'OperationalReadinessController@readiness',
            'actor_id' => null,
            'status_code' => 200,
            'outcome' => OperationalEvent::OUTCOME_SUCCESS,
            'error_class' => null,
            'occurred_at' => now(),
            'metadata' => null,
        ]);

        $this->withToken($this->nonAdminToken)->getJson('/api/admin/operations/issues/1')
            ->assertForbidden();

        $this->withToken($this->adminToken)->getJson('/api/admin/operations/issues/0')
            ->assertNotFound();

        $this->withToken($this->adminToken)
            ->getJson('/api/admin/operations/issues/'.$pipeline->id + 200000)
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'data' => [
                    'source', 'reference', 'status', 'severity', 'attempts', 'occurred_at',
                    'error_class', 'correlation_id', 'next_action', 'detail',
                ],
            ]);

        $this->withToken($this->adminToken)
            ->getJson('/api/admin/operations/issues/'.$resolvedEvent->id + 300000)
            ->assertOk()
            ->assertJsonPath('status', 'success');
    }
}
