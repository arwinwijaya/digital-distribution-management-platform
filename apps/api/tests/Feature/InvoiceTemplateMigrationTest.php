<?php

namespace Tests\Feature;

use Database\Seeders\InvoiceTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InvoiceTemplateMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_template_schema_and_default_corporate_row_are_seeded(): void
    {
        $this->assertTrue(Schema::hasTable('invoice_templates'));
        $this->assertEqualsCanonicalizing([
            'id', 'logo_path', 'company_name', 'address', 'npwp', 'primary_color',
            'footer_text', 'notes', 'signer_name', 'signer_title', 'show_npwp',
            'show_outlet_phone', 'created_at', 'updated_at',
        ], Schema::getColumnListing('invoice_templates'));

        $this->seed(InvoiceTemplateSeeder::class);

        $this->assertDatabaseCount('invoice_templates', 1);
        $this->assertDatabaseHas('invoice_templates', [
            'company_name' => 'PT Digital Distribusi Nusantara',
            'primary_color' => '#0F172A',
            'show_npwp' => true,
            'show_outlet_phone' => true,
        ]);

        $this->seed(InvoiceTemplateSeeder::class);
        $this->assertDatabaseCount('invoice_templates', 1);
    }
}
