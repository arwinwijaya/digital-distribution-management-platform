<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesVisit extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_user_id',
        'outlet_id',
        'target_id',
        'target',
        'visit_date',
        'scheduled_at',
        'check_in_at',
        'check_in_latitude',
        'check_in_longitude',
        'check_in_accuracy_m',
        'check_out_at',
        'check_out_latitude',
        'check_out_longitude',
        'status',
        'notes',
        'outcome',
    ];

    protected function casts(): array
    {
        return [
            'visit_date' => 'date:Y-m-d',
            'scheduled_at' => 'datetime',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'check_in_latitude' => 'decimal:7',
            'check_in_longitude' => 'decimal:7',
            'check_out_latitude' => 'decimal:7',
            'check_out_longitude' => 'decimal:7',
        ];
    }

    public function salesUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_user_id');
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }
}
