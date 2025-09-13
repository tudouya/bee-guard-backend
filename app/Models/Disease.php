<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Disease extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
    ];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'disease_product')
            ->withPivot(['priority', 'note'])
            ->withTimestamps();
    }
}
