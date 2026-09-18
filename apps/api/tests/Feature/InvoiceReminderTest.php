<?php

namespace Tests\Feature;

// Backward-compatible entrypoint for the T6 packet's original test command.
// Scenarios remain in focused files and are composed here only so PHPUnit can
// resolve the historical InvoiceReminderTest.php path directly.
require_once __DIR__.'/InvoiceReminderTimingTest.php';
require_once __DIR__.'/InvoiceReminderRetryTest.php';
require_once __DIR__.'/InvoiceReminderHistoryTest.php';
require_once __DIR__.'/Concurrency/InvoiceReminderPostgresConcurrencyTest.php';

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
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
     *
     * The trait's rollback callback is intentionally skipped (the concurrency
     * scenario spawns external workers that must keep seeing a migrated
     * schema), but that also means this class leaves its committed rows behind
     * and never clears the shared `RefreshDatabaseState::$migrated` flag. If a
     * previous RefreshDatabase test had set that flag, every later
     * RefreshDatabase class would then skip its own `migrate:fresh` and start
     * from this class' leaked data (surfacing as inflated user totals and
     * unexpected names in listing assertions). Resetting the flag at teardown
     * forces the next class to re-migrate, keeping the suite order-independent.
     */
    public function runDatabaseMigrations(): void
    {
        $this->artisan('migrate:fresh', $this->migrateFreshUsing());

        $this->beforeApplicationDestroyed(function (): void {
            RefreshDatabaseState::$migrated = false;
        });
    }
}
