<?php

namespace Tests\Feature;

// Backward-compatible entrypoint for the T6 packet's original test command.
// Scenarios remain in focused files and are composed here only so PHPUnit can
// resolve the historical InvoiceReminderTest.php path directly.
require_once __DIR__.'/InvoiceReminderTimingTest.php';
require_once __DIR__.'/InvoiceReminderRetryTest.php';
require_once __DIR__.'/InvoiceReminderHistoryTest.php';
require_once __DIR__.'/InvoiceReminderPostgresConcurrencyTest.php';

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\Feature\Support\InvoiceReminderFeatureSetup;
use Tests\Feature\Support\InvoiceReminderHistoryScenarios;
use Tests\Feature\Support\InvoiceReminderPostgresScenarios;
use Tests\Feature\Support\InvoiceReminderRetryScenarios;
use Tests\Feature\Support\InvoiceReminderTimingScenarios;
use Tests\Feature\Support\PostgresConcurrencyFeatureCase;

class InvoiceReminderTest extends PostgresConcurrencyFeatureCase
{
    use DatabaseMigrations;
    use InvoiceReminderFeatureSetup;
    use InvoiceReminderTimingScenarios;
    use InvoiceReminderRetryScenarios;
    use InvoiceReminderHistoryScenarios;
    use InvoiceReminderPostgresScenarios;

    protected bool $postgresRequired = false;
    protected string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpInvoiceReminderFeature();
    }

    /**
     * Reset before each compatibility test without registering the trait's
     * rollback callback, so the following explicit PostgreSQL command sees a
     * migrated schema.
     */
    public function runDatabaseMigrations(): void
    {
        $this->artisan('migrate:fresh', $this->migrateFreshUsing());
    }
}
