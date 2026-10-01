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

class InvoiceTemplateLogoValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RbacMatrixSeeder::class, InvoiceTemplateSeeder::class]);
        Storage::fake('public');
    }

    public function test_logo_validation_rejects_oversized_and_wrong_mime_and_preserves_prior_file(): void
    {
        $admin = User::factory()->admin()->create();
        $token = app(AuthService::class)->createToken($admin)['token'];

        // Initial update with valid logo
        $response = $this->withToken($token)->postJson('/api/admin/invoice-template', [
            'company_name' => 'PT Digital',
            'address' => 'Jakarta',
            'npwp' => '01.234.567.8-901.000',
            'primary_color' => '#0F172A',
            'logo' => UploadedFile::fake()->image('original.png', 100, 100),
        ]);

        $response->assertOk();
        $template = InvoiceTemplate::firstOrFail();
        $firstLogoPath = $template->logo_path;
        $this->assertNotNull($firstLogoPath);
        Storage::disk('public')->assertExists($firstLogoPath);

        // Attempt update with oversized file (>2MB, e.g. 3MB)
        $oversizedFile = UploadedFile::fake()->create('huge.jpg', 2500, 'image/jpeg');
        $this->withToken($token)->postJson('/api/admin/invoice-template', [
            'company_name' => 'PT Digital Updated',
            'address' => 'Jakarta',
            'npwp' => '01.234.567.8-901.000',
            'primary_color' => '#0F172A',
            'logo' => $oversizedFile,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['logo']);

        $template->refresh();
        $this->assertSame($firstLogoPath, $template->logo_path);
        $this->assertSame('PT Digital', $template->company_name); // transaction rollback or strict validation prevents partial save

        // Attempt update with invalid mime (pdf)
        $pdfFile = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');
        $this->withToken($token)->postJson('/api/admin/invoice-template', [
            'logo' => $pdfFile,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['logo']);

        $template->refresh();
        $this->assertSame($firstLogoPath, $template->logo_path);
    }
}
