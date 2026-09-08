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
        'status',
        'notes',
        'outcome',
    ];

    protected function casts(): array
    {
        return [
            'visit_date' => 'date:Y-m-d',
            'scheduled_at' => 'datetime',
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
