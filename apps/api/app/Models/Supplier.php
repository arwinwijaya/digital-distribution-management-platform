<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;

    public const SUBSCRIPTION_STATUSES = ['active', 'inactive'];
    public const SUBSCRIPTION_PLANS = ['basic', 'premium'];

    protected $fillable = [
        'user_id',
        'name',
        'subscription_status',
        'subscription_plan',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
