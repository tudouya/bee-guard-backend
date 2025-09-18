<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'detection_code_id',
        'courier_company',
        'tracking_no',
        'shipped_at',
        'contact_phone',
    ];

    protected $casts = [
        'shipped_at' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function detectionCode(): BelongsTo
    {
        return $this->belongsTo(DetectionCode::class);
    }
}
