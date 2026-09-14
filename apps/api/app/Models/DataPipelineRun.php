<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DataPipelineRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'run_uuid',
        'status',
        'pipeline_version',
        'window_start',
        'window_end',
        'timezone',
        'lineage',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'window_start' => 'date',
            'window_end' => 'date',
            'lineage' => 'array',
        ];
    }

    public function snapshot(): HasOne
    {
        return $this->hasOne(DataSnapshot::class, 'run_id');
    }
}
