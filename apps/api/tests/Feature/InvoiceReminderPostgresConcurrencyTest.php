<?php

namespace Tests\Feature;

use Tests\Feature\Support\InvoiceReminderPostgresScenarios;
use Tests\Feature\Support\PostgresConcurrencyFeatureCase;

/**
 * Requires a dedicated migrated PostgreSQL test database and exercises two
 * independent artisan reminder workers plus a real provider boundary.
 */
class InvoiceReminderPostgresConcurrencyTest extends PostgresConcurrencyFeatureCase
{
    use InvoiceReminderPostgresScenarios;
}
