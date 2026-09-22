<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'vehicle_type',
        'plate_number',
        'capacity_kg',
        'service_territory_id',
        'shift_start',
        'shift_end',
        'is_available',
    ];

    protected function casts(): array
    {
        return [
            'capacity_kg' => 'integer',
            'is_available' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function serviceTerritory(): BelongsTo
    {
        return $this->belongsTo(Territory::class, 'service_territory_id');
    }
}
