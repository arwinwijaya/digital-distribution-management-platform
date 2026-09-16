<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use InvalidArgumentException;

class Outlet extends Model
{
    public const DEFAULT_PAYMENT_TERM_DAYS = 7;

    /**
     * Allowed outlet category values.
     *
     * @var list<string>
     */
    public const VALID_CATEGORIES = [
        'warung',
        'minimarket',
        'supermarket',
        'grosir',
        'restoran',
        'kafe',
        'toko_kelontong',
        'lainnya',
    ];

    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'phone',
        'address',
        'city',
        'district',
        'latitude',
        'longitude',
        'user_id',
        'territory_id',
        'is_active',
        'payment_term_days',
        'category',
        'score',
    ];

    protected static function booted(): void
    {
        static::creating(function (Outlet $outlet): void {
            $outlet->category ??= 'lainnya';
            $outlet->score ??= 0;
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'is_active' => 'boolean',
            'payment_term_days' => 'integer',
            'score' => 'integer',
        ];
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInTerritory($query, ?int $territoryId)
    {
        if ($territoryId !== null) {
            return $query->where('territory_id', $territoryId);
        }
        return $query;
    }

    public function scopeOfCategory($query, ?string $category)
    {
        if ($category !== null && in_array($category, self::VALID_CATEGORIES, true)) {
            return $query->where('category', $category);
        }
        return $query;
    }

    public function scopeSearch($query, ?string $search)
    {
        if ($search !== null && $search !== '') {
            return $query->where('name', 'like', "%{$search}%");
        }
        return $query;
    }

    protected function setPhoneAttribute(mixed $value): void
    {
        $canonical = static::canonicalizePhone((string) $value);
        if ($canonical === '') {
            throw new InvalidArgumentException('Outlet phone must contain digits.');
        }
        $this->attributes['phone'] = trim((string) $value);
        $this->attributes['canonical_phone'] = $canonical;
    }

    public static function canonicalizePhone(string $phone): string
    {
        $digits = preg_replace('/\\D+/', '', $phone) ?? '';
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }

        return $digits === '' ? '' : '+'.$digits;
    }

    /**
     * Get the user that owns the outlet.
     */
    public function effectivePaymentTermDays(): int
    {
        return $this->payment_term_days ?? self::DEFAULT_PAYMENT_TERM_DAYS;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function territory(): BelongsTo
    {
        return $this->belongsTo(Territory::class);
    }

    public function creditLimit(): HasOne
    {
        return $this->hasOne(CreditLimit::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
