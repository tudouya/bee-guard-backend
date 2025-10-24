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
        'map_alias',
        'map_color',
        'map_order',
        'description',
        'brief',
        'symptom',
        'transmit',
        'prevention',
        'status',
        'sort',
    ];

    protected $casts = [
        'map_order' => 'integer',
    ];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'disease_product')
            ->withPivot(['priority', 'note'])
            ->withTimestamps();
    }

    public function knowledgeArticles()
    {
        return $this->hasMany(KnowledgeArticle::class);
    }
}
