<?php

namespace Tests\Feature\Support;

use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class InvoiceReminderFeatureCase extends TestCase
{
    use RefreshDatabase;
    use InvoiceReminderFeatureSetup;

    protected Outlet $outlet;
    protected User $admin;
    protected string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpInvoiceReminderFeature();
    }
}
