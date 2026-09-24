<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AbExperiment extends Model
{
    use HasFactory;

    public const STATUSES = ['draft', 'running', 'paused', 'completed', 'cancelled'];

    protected $fillable = [
        'experiment_key',
        'name',
        'status',
        'minimum_sample_size',
        'configuration',
        'created_by',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'minimum_sample_size' => 'integer',
            'configuration' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(AbExperimentAssignment::class, 'experiment_id');
    }

    public function revenueLiftSnapshots(): HasMany
    {
        return $this->hasMany(RevenueLiftSnapshot::class, 'experiment_id');
    }
}
