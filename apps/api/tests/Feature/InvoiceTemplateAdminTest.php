<?php

namespace Tests\Feature;

use App\Models\InvoiceTemplate;
use App\Models\User;
use App\Services\AuthService;
use Database\Seeders\InvoiceTemplateSeeder;
use Database\Seeders\RbacMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvoiceTemplateAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RbacMatrixSeeder::class, InvoiceTemplateSeeder::class]);
        Storage::fake('public');
    }

    public function test_admin_can_read_and_update_single_template_with_logo(): void
    {
        $admin = User::factory()->admin()->create();
        $token = app(AuthService::class)->createToken($admin)['token'];

        $this->withToken($token)->getJson('/api/admin/invoice-template')
            ->assertOk()
            ->assertJsonPath('data.company_name', 'PT Digital Distribusi Nusantara');

        $this->withToken($token)->postJson('/api/admin/invoice-template', [
            'company_name' => 'Updated Company',
            'address' => 'Jakarta',
            'npwp' => '01.234.567.8-901.000',
            'primary_color' => '#123ABC',
            'footer_text' => 'Thank you',
            'notes' => 'Invoice notes',
            'signer_name' => 'Jane Doe',
            'signer_title' => 'Finance Director',
            'show_npwp' => true,
            'show_outlet_phone' => false,
            'logo' => UploadedFile::fake()->image('logo.jpg'),
        ])->assertOk()
            ->assertJsonPath('data.company_name', 'Updated Company');

        $this->assertDatabaseCount('invoice_templates', 1);
        $template = InvoiceTemplate::firstOrFail();
        $this->assertSame('Updated Company', $template->company_name);
        $this->assertNotNull($template->logo_path);
        Storage::disk('public')->assertExists($template->logo_path);
    }

    public function test_finance_can_read_but_cannot_update_and_outlet_cannot_read(): void
    {
        $financeToken = app(AuthService::class)->createToken(User::factory()->finance()->create())['token'];
        $this->withToken($financeToken)->getJson('/api/admin/invoice-template')->assertOk();
        $this->withToken($financeToken)->postJson('/api/admin/invoice-template', [])->assertForbidden();

        $outletToken = app(AuthService::class)->createToken(User::factory()->outlet()->create())['token'];
        $this->withToken($outletToken)->getJson('/api/admin/invoice-template')->assertForbidden();
    }
}
