<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EpidemicMapDatasetEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'dataset_id',
        'month',
        'disease_code',
        'positive_cases',
        'sample_total',
        'rate',
        'remark',
    ];

    protected $casts = [
        'month' => 'integer',
        'positive_cases' => 'integer',
        'sample_total' => 'integer',
        'rate' => 'float',
    ];

    protected static function booted(): void
    {
        static::saving(function (EpidemicMapDatasetEntry $entry) {
            $entry->positive_cases = max(0, min($entry->positive_cases ?? 0, $entry->sample_total ?? 0));
            if ($entry->sample_total > 0) {
                $rate = $entry->positive_cases / $entry->sample_total;
                $entry->rate = round(min(max($rate, 0), 1), 5);
            } else {
                $entry->rate = null;
            }
        });
    }

    public function dataset(): BelongsTo
    {
        return $this->belongsTo(EpidemicMapDataset::class, 'dataset_id');
    }
}
