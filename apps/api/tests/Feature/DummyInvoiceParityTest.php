<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DummyInvoiceParityTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\InvoiceTemplateSeeder::class);

        $this->adminUser = User::factory()->admin()->create([
            'email' => 'dummy-parity-admin@example.test',
            'password' => Hash::make('password123'),
        ]);
        $this->adminToken = $this->login($this->adminUser);
    }

    /**
     * RED: dummy mode detail should return same JSON shape as real mode.
     * Expected RED before implementation: dummy returns 404 or missing snapshot/payments.
     */
    public function test_dummy_detail_shape_equals_real_mode(): void
    {
        // Seed deterministic dummy data
        $this->seed(\Database\Seeders\DummySeeder::class);
        $dummyInvoice = Invoice::where('invoice_number', 'INV-DUMMY-0001')->firstOrFail();

        // Enable dummy mode
        config(['app.dummy_mode' => true]);

        // Request detail in dummy mode
        $dummyResponse = $this->withToken($this->adminToken)
            ->getJson("/api/invoices/{$dummyInvoice->id}");
        $dummyResponse->assertOk();
        $dummyData = $dummyResponse->json('data');

        // Disable dummy mode for parity comparison
        config(['app.dummy_mode' => false]);

        // Request same invoice in real mode
        $realResponse = $this->withToken($this->adminToken)
            ->getJson("/api/invoices/{$dummyInvoice->id}");
        $realResponse->assertOk();
        $realData = $realResponse->json('data');

        // Assert JSON shapes match exactly (recursive key comparison)
        $this->assertArrayKeysMatch($realData, $dummyData);

        // Specific assertions for critical fields
        $this->assertArrayHasKey('product_name_snapshot', $dummyData['line_items'][0] ?? []);
        $this->assertCount(3, $dummyData['payments'] ?? []);
        $statuses = array_column($dummyData['payments'] ?? [], 'status');
        $this->assertContains('completed', $statuses);
        $this->assertContains('pending', $statuses);
        $this->assertContains('failed', $statuses);

        // Payments ordered newest first (by created_at DESC)
        $paymentIds = array_column($dummyData['payments'] ?? [], 'id');
        $p1 = Payment::find($paymentIds[0]);
        $p2 = Payment::find($paymentIds[1]);
        $p3 = Payment::find($paymentIds[2]);
        $this->assertTrue($p2->created_at < $p1->created_at);
        $this->assertTrue($p3->created_at < $p2->created_at);

        // Totals present
        $this->assertArrayHasKey('total_amount', $dummyData);
        $this->assertArrayHasKey('paid_amount', $dummyData);
        $this->assertArrayHasKey('balance_amount', $dummyData);

        // Badge present
        $this->assertArrayHasKey('is_overdue', $dummyData);
    }

    protected function login(User $user): string
    {
        return $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertOk()->json('data.token');
    }

    /**
     * Recursively assert that $expected contains all keys of $actual and vice versa.
     */
    private function assertArrayKeysMatch(array $expected, array $actual, string $path = ''): void
    {
        $expectedKeys = array_keys($expected);
        $actualKeys = array_keys($actual);

        $this->assertEquals(
            $expectedKeys,
            $actualKeys,
            "Keys mismatch at {$path}: expected [" . implode(', ', $expectedKeys) . "] got [" . implode(', ', $actualKeys) . "]"
        );

        foreach ($expectedKeys as $key) {
            $newPath = $path ? "{$path}.{$key}" : $key;
            $e = $expected[$key];
            $a = $actual[$key];

            if (is_array($e) && is_array($a)) {
                // For lists of objects, compare first element shape
                $firstE = array_values($e)[0] ?? null;
                $firstA = array_values($a)[0] ?? null;
                if (is_array($firstE) && is_array($firstA) && $this->isAssoc($firstE) && $this->isAssoc($firstA)) {
                    $this->assertArrayKeysMatch($firstE, $firstA, $newPath . '[0]');
                }
            }
        }
    }

    private function isAssoc(array $arr): bool
    {
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}