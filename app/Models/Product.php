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
        'status',
        'homepage_featured',
        'homepage_sort_order',
        'homepage_registration_no',
        'homepage_applicable_scene',
        'homepage_highlights',
        'homepage_cautions',
        'homepage_price',
        'homepage_contact_company',
        'homepage_contact_phone',
        'homepage_contact_wechat',
        'homepage_contact_website',
    ];

    protected $casts = [
        'homepage_featured' => 'boolean',
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

    public function homepageImages()
    {
        return $this->hasMany(ProductHomepageImage::class)->orderBy('position');
    }
}
