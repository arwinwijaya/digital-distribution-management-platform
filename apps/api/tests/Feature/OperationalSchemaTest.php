<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OperationalSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_schema_contract_is_relational_reversible_and_domain_typed(): void
    {
        $this->assertTrue(Schema::hasTable('invoices'));
        $this->assertTrue(Schema::hasTable('invoice_reminders'));
        $this->assertTrue(Schema::hasTable('role_assignment_audits'));
        $this->assertTrue(Schema::hasColumn('outlets', 'payment_term_days'));
        $this->assertTrue($this->columnIsNullable('outlets', 'payment_term_days'));

        $this->assertTrue(Schema::hasColumns('invoices', [
            'order_id', 'outlet_id', 'issue_date', 'due_date', 'total_amount',
            'paid_amount', 'balance_amount', 'status',
        ]));
        $this->assertTrue(Schema::hasColumns('invoice_reminders', [
            'invoice_id', 'event_type', 'event_date', 'status', 'attempts',
            'next_attempt_at', 'idempotency_key',
        ]));
        $this->assertTrue(Schema::hasColumns('role_assignment_audits', [
            'target_user_id', 'actor_user_id', 'from_role', 'to_role', 'action',
        ]));

        $this->assertTrue(class_exists(\App\Models\Invoice::class));
        $this->assertTrue(class_exists(\App\Models\InvoiceReminder::class));
        $this->assertTrue(class_exists(\App\Models\RoleAssignmentAudit::class));
        $this->assertInstanceOf(\App\Models\Invoice::class, \App\Models\Invoice::factory()->make());
        $this->assertInstanceOf(\App\Models\InvoiceReminder::class, \App\Models\InvoiceReminder::factory()->make());

        $invoice = new \App\Models\Invoice();
        $this->assertSame('decimal:2', $invoice->getCasts()['total_amount']);
        $this->assertSame('decimal:2', $invoice->getCasts()['paid_amount']);
        $this->assertSame('decimal:2', $invoice->getCasts()['balance_amount']);
        $this->assertSame('date', $invoice->getCasts()['issue_date']);
        $this->assertSame('date', $invoice->getCasts()['due_date']);
        $this->assertSame(\App\Models\Invoice::UNPAID, 'unpaid');
        $this->assertSame(\App\Models\Invoice::PARTIALLY_PAID, 'partially_paid');
        $this->assertSame(\App\Models\Invoice::PAID, 'paid');
        $this->assertSame(\App\Models\Invoice::CANCELLED, 'cancelled');

        $reminder = new \App\Models\InvoiceReminder();
        $this->assertSame('date', $reminder->getCasts()['event_date']);
        $this->assertSame('datetime', $reminder->getCasts()['next_attempt_at']);
        $this->assertSame('array', $reminder->getCasts()['metadata']);
        $this->assertSame('array', (new \App\Models\RoleAssignmentAudit())->getCasts()['metadata']);

        $this->assertTrue($this->hasIndex('invoices', ['order_id'], true));
        $this->assertTrue($this->hasIndex('invoices', ['outlet_id', 'status']));
        $this->assertTrue($this->hasIndex('invoices', ['status', 'due_date']));
        $this->assertTrue($this->hasIndex('invoice_reminders', ['invoice_id', 'event_type', 'event_date'], true));
        $this->assertTrue($this->hasIndex('invoice_reminders', ['status', 'next_attempt_at']));
        $this->assertTrue($this->hasIndex('role_assignment_audits', ['target_user_id', 'created_at']));
        $this->assertTrue($this->hasIndex('role_assignment_audits', ['actor_user_id', 'created_at']));

        $this->assertTrue($this->hasForeignKey('invoices', 'order_id'));
        $this->assertTrue($this->hasForeignKey('invoices', 'outlet_id'));
        $this->assertTrue($this->hasForeignKey('invoice_reminders', 'invoice_id'));
        $this->assertTrue($this->hasForeignKey('role_assignment_audits', 'target_user_id'));
        $this->assertTrue($this->hasForeignKey('role_assignment_audits', 'actor_user_id'));

        foreach ([
            '2026_09_10_000019_add_payment_term_days_to_outlets_table.php',
            '2026_09_10_000020_create_invoices_table.php',
            '2026_09_10_000021_create_invoice_reminders_table.php',
            '2026_09_10_000022_create_role_assignment_audits_table.php',
        ] as $migration) {
            $path = database_path('migrations/'.$migration);
            $instance = require $path;
            $this->assertTrue(method_exists($instance, 'down'), $migration.' must define down().');
        }
    }

    private function columnIsNullable(string $table, string $column): bool
    {
        foreach (Schema::getColumns($table) as $definition) {
            if ($definition['name'] === $column) {
                return $definition['nullable'];
            }
        }

        return false;
    }

    private function hasIndex(string $table, array $columns, bool $unique = false): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if ($index['columns'] === $columns && (!$unique || $index['unique'])) {
                return true;
            }
        }

        return false;
    }

    private function hasForeignKey(string $table, string $column): bool
    {
        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            if ($foreignKey['columns'] === [$column]) {
                return true;
            }
        }

        return false;
    }
}
