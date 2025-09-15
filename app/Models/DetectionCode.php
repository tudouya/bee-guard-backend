<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DetectionCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'source_type',
        'prefix',
        'status',
        'enterprise_id',
        'assigned_user_id',
        'assigned_at',
        'used_at',
        'meta',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'used_at' => 'datetime',
        'meta' => 'array',
    ];

    public function enterprise(): BelongsTo
    {
        return $this->belongsTo(Enterprise::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function shippingNotifications(): HasMany
    {
        return $this->hasMany(ShippingNotification::class);
    }

    protected static function booted(): void
    {
        static::saving(function (DetectionCode $code) {
            // If status is explicitly set to available, ensure no assignee data remains
            if ($code->status === 'available') {
                $code->assigned_user_id = null;
                $code->assigned_at = null;
            }

            // If there is an assignee, enforce status=assigned and assigned_at present
            if (!empty($code->assigned_user_id)) {
                if ($code->status !== 'assigned') {
                    $code->status = 'assigned';
                }
                if (empty($code->assigned_at)) {
                    $code->assigned_at = now();
                }
            }

            // If moved to used and no used_at set, stamp it
            if ($code->status === 'used' && empty($code->used_at)) {
                $code->used_at = now();
            }
        });
    }
}
