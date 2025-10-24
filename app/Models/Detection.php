<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Detection extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'detection_code_id',
        'sample_no',
        'contact_name',
        'sample_type',
        'address_text',
        'province_code',
        'city_code',
        'district_code',
        'sampled_at',
        'submitted_at',
        'status',
        'tested_at',
        'tested_by',
        'report_no',
        'lab_notes',
        'reported_at',
        'questionnaire',
        'contact_phone',
        // wide-table levels
        'rna_iapv_level','rna_bqcv_level','rna_sbv_level','rna_abpv_level','rna_cbpv_level','rna_dwv_level',
        'dna_afb_level','dna_efb_level','dna_ncer_level','dna_napi_level','dna_cb_level',
        'pest_large_mite',
        'pest_small_mite',
        'pest_wax_moth',
        'pest_small_hive_beetle',
        'pest_shield_mite',
        'pest_scoliidae_wasp',
        'pest_parasitic_bee_fly',
    ];

    protected $casts = [
        'sampled_at' => 'datetime',
        'submitted_at' => 'datetime',
        'tested_at' => 'datetime',
        'reported_at' => 'datetime',
        'province_code' => 'string',
        'city_code' => 'string',
        'district_code' => 'string',
        'questionnaire' => 'array',
        'pest_large_mite' => 'boolean',
        'pest_small_mite' => 'boolean',
        'pest_wax_moth' => 'boolean',
        'pest_small_hive_beetle' => 'boolean',
        'pest_shield_mite' => 'boolean',
        'pest_scoliidae_wasp' => 'boolean',
        'pest_parasitic_bee_fly' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function detectionCode(): BelongsTo
    {
        return $this->belongsTo(DetectionCode::class);
    }

    // 宽表模式下不再有子表结果
}
