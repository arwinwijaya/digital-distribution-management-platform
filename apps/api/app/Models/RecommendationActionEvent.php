<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 9 audit trail — append-only by convention.
 *
 * No update/delete paths exist in the codebase for this model; rows are
 * written once at creation time and read afterwards.
 */
class RecommendationActionEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'recommendation_action_id',
        'event_type',
        'actor_id',
        'metadata',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function action(): BelongsTo
    {
        return $this->belongsTo(RecommendationAction::class, 'recommendation_action_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
