<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RevenueLiftSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'experiment_id',
        'uplift',
        'status',
        'method_version',
        'control_sample_size',
        'treatment_sample_size',
        'control_revenue',
        'treatment_revenue',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'uplift' => 'decimal:6',
            'control_sample_size' => 'integer',
            'treatment_sample_size' => 'integer',
            'control_revenue' => 'decimal:2',
            'treatment_revenue' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function experiment(): BelongsTo
    {
        return $this->belongsTo(AbExperiment::class, 'experiment_id');
    }
}
