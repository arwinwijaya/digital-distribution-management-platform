<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbExperimentAssignment extends Model
{
    use HasFactory;

    public const BUCKETS = ['control', 'treatment'];

    protected $fillable = [
        'experiment_id',
        'subject_key',
        'bucket',
        'assigned_at',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
        ];
    }

    public function experiment(): BelongsTo
    {
        return $this->belongsTo(AbExperiment::class, 'experiment_id');
    }
}
