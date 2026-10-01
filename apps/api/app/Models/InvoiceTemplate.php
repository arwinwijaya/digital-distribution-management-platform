<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceTemplate extends Model
{
    protected $fillable = [
        'logo_path',
        'company_name',
        'address',
        'npwp',
        'primary_color',
        'footer_text',
        'notes',
        'signer_name',
        'signer_title',
        'show_npwp',
        'show_outlet_phone',
    ];

    protected function casts(): array
    {
        return [
            'show_npwp' => 'boolean',
            'show_outlet_phone' => 'boolean',
        ];
    }
}
