<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryStatusHistory extends Model
{
    protected $fillable = [
        'delivery_id',
        'actor_id',
        'from_status',
        'status',
        'metadata',
        'notes',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
