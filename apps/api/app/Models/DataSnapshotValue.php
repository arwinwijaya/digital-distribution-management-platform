<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataSnapshotValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'snapshot_id',
        'metric_definition_id',
        'section',
        'dimension_key',
        'dimension',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'dimension' => 'array',
            'value' => 'array',
        ];
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(DataSnapshot::class, 'snapshot_id');
    }

    public function metricDefinition(): BelongsTo
    {
        return $this->belongsTo(DataMetricDefinition::class, 'metric_definition_id');
    }
}
