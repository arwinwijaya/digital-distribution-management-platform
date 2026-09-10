<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user is outlet
     */
    public function isOutlet(): bool
    {
        return $this->role === 'outlet';
    }

    /**
     * Check if user is supplier
     */
    public function isSupplier(): bool
    {
        return $this->role === 'supplier';
    }

    public function isSales(): bool
    {
        return $this->role === 'sales';
    }

    public function isDriver(): bool
    {
        return $this->role === 'driver';
    }

    public function isFinance(): bool
    {
        return app(\App\Services\FinanceAuthorizationService::class)->isFinance($this);
    }

    /**
     * Get the outlet associated with this user.
     */
    public function outlet(): HasOne
    {
        return $this->hasOne(Outlet::class);
    }

    /**
     * Get the supplier associated with this user.
     */
    public function supplier(): HasOne
    {
        return $this->hasOne(Supplier::class);
    }

    public function salesVisits(): HasMany
    {
        return $this->hasMany(SalesVisit::class, 'sales_user_id');
    }

    public function assignedDeliveries(): HasMany
    {
        return $this->hasMany(Delivery::class, 'driver_id');
    }

    public function createdDeliveries(): HasMany
    {
        return $this->hasMany(Delivery::class, 'assigned_by_id');
    }

    public function roleAssignmentAudits(): HasMany
    {
        return $this->hasMany(RoleAssignmentAudit::class, 'target_user_id');
    }

    public function roleAssignmentsMade(): HasMany
    {
        return $this->hasMany(RoleAssignmentAudit::class, 'actor_user_id');
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     */
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     */
    public function getJWTCustomClaims(): array
    {
        return [
            'role' => $this->role,
        ];
    }
}
