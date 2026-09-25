<?php

namespace Tests\Feature;

use App\Models\MenuDefinition;
use App\Models\RoleMenuAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test-infrastructure guard: every RefreshDatabase test gets the default RBAC
 * matrix without calling the seeder explicitly, so that once the Rbac middleware
 * is active the existing suite is not blanket-403'd by missing rows.
 */
class RbacTestInfraTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_matrix_is_available_without_explicit_seeding(): void
    {
        $this->assertSame(23, MenuDefinition::count());
        $this->assertSame(
            'edit',
            RoleMenuAccess::where('role', 'admin')->where('menu_key', 'products')->value('level'),
        );
    }

    public function test_finance_read_level_is_seeded(): void
    {
        $this->assertSame(
            'read',
            RoleMenuAccess::where('role', 'finance')->where('menu_key', 'products')->value('level'),
        );
    }
}
