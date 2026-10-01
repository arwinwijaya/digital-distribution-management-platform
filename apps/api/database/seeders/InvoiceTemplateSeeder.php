<?php

namespace Database\Seeders;

use App\Models\InvoiceTemplate;
use Illuminate\Database\Seeder;

/**
 * Seeds the default corporate invoice template (single active row).
 *
 * PT Digital Distribusi Nusantara — Navy/Slate palette.
 */
class InvoiceTemplateSeeder extends Seeder
{
    public function run(): void
    {
        InvoiceTemplate::updateOrCreate(
            ['company_name' => 'PT Digital Distribusi Nusantara'],
            [
                'logo_path' => null,
                'address' => 'Jl. Jend. Sudirman Kav. 52-53, Jakarta Pusat 12190',
                'npwp' => '01.234.567.8-901.000',
                'primary_color' => '#0F172A', // Navy/Slate (slate-900)
                'footer_text' => 'Terima kasih atas kepercayaan Anda. | PT Digital Distribusi Nusantara',
                'notes' => 'Invoice ini merupakan dokumen resmi pembayaran.',
                'signer_name' => 'Budi Santoso',
                'signer_title' => 'Direktur Keuangan',
                'show_npwp' => true,
                'show_outlet_phone' => true,
            ]
        );
    }
}