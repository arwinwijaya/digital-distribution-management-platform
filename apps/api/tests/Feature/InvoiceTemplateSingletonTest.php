<?php

namespace Tests\Feature;

use Database\Seeders\InvoiceTemplateSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InvoiceTemplateSingletonTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_rejects_a_second_template_even_with_a_different_singleton_value(): void
    {
        $this->seed(InvoiceTemplateSeeder::class);

        try {
            DB::table('invoice_templates')->insert([
                'singleton' => 'y',
                'company_name' => 'Second template',
                'address' => 'Second address',
                'npwp' => '98.765.432.1-000.000',
                'primary_color' => '#FFFFFF',
                'show_npwp' => true,
                'show_outlet_phone' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (QueryException $exception) {
            $this->assertDatabaseCount('invoice_templates', 1);

            return;
        }

        $this->fail('The database accepted a second invoice template with singleton = y.');
    }
}
