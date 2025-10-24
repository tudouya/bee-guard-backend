<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EpidemicBulletin extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'summary',
        'content',
        'risk_level',
        'status',
        'published_at',
        'source',
        'attachments',
        'province_code',
        'city_code',
        'district_code',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'attachments' => 'array',
    ];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';

    public const RISK_HIGH = 'high';
    public const RISK_MEDIUM = 'medium';
    public const RISK_LOW = 'low';

    protected static function booted(): void
    {
        static::saving(function (EpidemicBulletin $bulletin) {
            if ($bulletin->status === self::STATUS_PUBLISHED && empty($bulletin->published_at)) {
                $bulletin->published_at = now();
            }
        });
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
