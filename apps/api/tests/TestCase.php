<?php

namespace Tests;

use Database\Seeders\RbacMatrixSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }

    /**
     * Seed the default RBAC matrix once the database is available.
     *
     * The RBAC middleware treats a missing (role, menu_key) row as `none`,
     * so without this every authenticated test request would 403. Seeding
     * here keeps the ~49 existing RefreshDatabase tests green without each
     * one having to call the seeder. Guarded by Schema::hasTable so tests
     * that never touch the database (no RefreshDatabase) are unaffected.
     *
     * Runs after parent::setUp() so RefreshDatabase has already migrated.
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (Schema::hasTable('menu_definitions')) {
            $this->seed(RbacMatrixSeeder::class);
        }
    }
}
