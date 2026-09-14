<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'run_id',
        'snapshot_uuid',
        'version',
        'status',
        'is_active',
        'window_start',
        'window_end',
        'timezone',
        'lineage',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'is_active' => 'boolean',
            'window_start' => 'date',
            'window_end' => 'date',
            'lineage' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(DataPipelineRun::class, 'run_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(DataSnapshotValue::class, 'snapshot_id');
    }
}
