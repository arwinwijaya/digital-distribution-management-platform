<?php

namespace Tests\Feature;

use App\Models\RoleMenuAccess;
use Database\Seeders\RbacMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceTemplateRbacSeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_template_rbac_grants_admin_edit_and_finance_read_only(): void
    {
        $this->seed(RbacMatrixSeeder::class);

        $this->assertSame('edit', $this->level('admin'));
        $this->assertSame('read', $this->level('finance'));
        $this->assertSame('none', $this->level('platform_owner'));
        $this->assertSame('none', $this->level('outlet'));
        $this->assertSame('none', $this->level('supplier'));
        $this->assertSame('none', $this->level('sales'));
        $this->assertSame('none', $this->level('driver'));

        $this->assertSame(2, RoleMenuAccess::where('menu_key', 'invoice_template')->count());
    }

    private function level(string $role): string
    {
        return RoleMenuAccess::where('role', $role)
            ->where('menu_key', 'invoice_template')
            ->value('level') ?? 'none';
    }
}
