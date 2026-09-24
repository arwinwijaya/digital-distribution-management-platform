<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ForecastCalibration extends Model
{
    use HasFactory;

    protected $fillable = [
        'dimension_key',
        'bias_factor',
        'seasonality_factor',
        'method_version',
        'fallback',
        'sample_size',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'bias_factor' => 'decimal:6',
            'seasonality_factor' => 'decimal:6',
            'fallback' => 'boolean',
            'sample_size' => 'integer',
            'metadata' => 'array',
        ];
    }
}
