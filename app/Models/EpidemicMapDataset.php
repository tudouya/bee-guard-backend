<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EpidemicMapDataset extends Model
{
    use HasFactory;

    protected $fillable = [
        'year',
        'province_code',
        'city_code',
        'district_code',
        'source_type',
        'locked',
        'source',
        'notes',
        'data_updated_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'year' => 'integer',
        'data_updated_at' => 'datetime',
        'locked' => 'boolean',
    ];

    public function entries(): HasMany
    {
        return $this->hasMany(EpidemicMapDatasetEntry::class, 'dataset_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
