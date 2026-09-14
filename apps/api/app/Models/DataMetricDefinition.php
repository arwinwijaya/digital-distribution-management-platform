<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataMetricDefinition extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'name',
        'description',
        'method_version',
        'definition',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'definition' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function snapshotValues(): HasMany
    {
        return $this->hasMany(DataSnapshotValue::class, 'metric_definition_id');
    }
}
