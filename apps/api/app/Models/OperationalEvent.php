<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OperationalEvent extends Model
{
    use HasFactory;

    public const OUTCOME_SUCCESS = 'success';

    public const OUTCOME_FAILURE = 'failure';

    protected $fillable = [
        'correlation_id',
        'route',
        'action',
        'actor_id',
        'status_code',
        'outcome',
        'error_class',
        'occurred_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'actor_id' => 'integer',
            'status_code' => 'integer',
            'occurred_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
