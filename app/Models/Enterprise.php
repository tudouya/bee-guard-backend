<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Enterprise extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_user_id',
        'name',
        'contact_name',
        'contact_phone',
        'status',
        'code_prefix',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }
}
