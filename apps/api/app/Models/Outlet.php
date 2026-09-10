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
        'is_active',
    ];

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
        ];
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
