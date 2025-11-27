<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetectionResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'detection_id',
        'disease_id',
        'level',
        'source',
        'remark',
    ];

    protected $casts = [
        'detection_id' => 'integer',
        'disease_id' => 'integer',
    ];

    public function detection(): BelongsTo
    {
        return $this->belongsTo(Detection::class);
    }

    public function disease(): BelongsTo
    {
        return $this->belongsTo(Disease::class);
    }
}
