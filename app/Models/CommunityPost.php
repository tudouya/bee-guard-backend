<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class CommunityPost extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'content',
        'content_format',
        'images',
        'disease_id',
        'category',
        'status',
        'reject_reason',
        'views',
        'likes',
        'replies_count',
        'published_at',
        'reviewed_at',
        'reviewed_by',
    ];

    protected function casts(): array
    {
        return [
            'images' => 'array',
            'published_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function disease(): BelongsTo
    {
        return $this->belongsTo(Disease::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(CommunityPostReply::class, 'post_id');
    }

    public function likesRelation(): HasMany
    {
        return $this->hasMany(CommunityPostLike::class, 'post_id');
    }

    public function approve(User $reviewer): void
    {
        if ($this->trashed()) {
            $this->restore();
            $this->refresh();
        }

        $now = Carbon::now();
        $this->status = 'approved';
        $this->reject_reason = null;
        $this->published_at = $this->published_at ?: $now;
        $this->reviewed_at = $now;
        $this->reviewed_by = $reviewer->id;
        $this->save();

        $this->refreshReplyCount();
    }

    public function reject(User $reviewer, string $reason): void
    {
        $this->status = 'rejected';
        $this->reject_reason = $reason;
        $this->reviewed_at = Carbon::now();
        $this->reviewed_by = $reviewer->id;
        $this->published_at = null;
        $this->save();
    }

    public function refreshReplyCount(): void
    {
        $count = $this->replies()
            ->where('status', 'approved')
            ->count();

        $this->replies_count = $count;
        $this->save();
    }
}
