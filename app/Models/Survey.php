<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Survey extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'detection_code_id',
        'fill_date',
        'fill_time',
        'owner_name',
        'location_name',
        'location_latitude',
        'location_longitude',
        'phone',
        'bee_count',
        'raise_method',
        'bee_species',
        'income_ranks',
        'is_production_now',
        'product_type',
        'honey_type',
        'pollen_type',
        'next_month',
        'need_move',
        'move_province',
        'move_city',
        'move_district',
        'next_floral',
        'has_abnormal',
        'sick_ages',
        'sick_count',
        'symptoms',
        'symptom_other',
        'medications',
        'occur_rule',
        'possible_reason',
        'past_months',
        'submitted_at',
        'status',
    ];

    protected $casts = [
        'fill_date' => 'date',
        'fill_time' => 'datetime:H:i',
        'location_latitude' => 'decimal:8',
        'location_longitude' => 'decimal:8',
        'bee_count' => 'integer',
        'sick_count' => 'integer',
        'income_ranks' => 'array',
        'sick_ages' => 'array',
        'symptoms' => 'array',
        'medications' => 'array',
        'past_months' => 'array',
        'submitted_at' => 'datetime',
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