<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'enterprise_id',
        'name',
        'brief',
        'url',
        'media',
        'status',
    ];

    protected $casts = [
        'media' => 'array',
    ];

    public function enterprise(): BelongsTo
    {
        return $this->belongsTo(Enterprise::class);
    }

    public function diseases()
    {
        return $this->belongsToMany(Disease::class, 'disease_product')
            ->withPivot(['priority', 'note'])
            ->withTimestamps();
    }
}
