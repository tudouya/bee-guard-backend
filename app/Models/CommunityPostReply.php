<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class CommunityPostReply extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'post_id',
        'user_id',
        'parent_id',
        'content',
        'status',
        'reject_reason',
        'reply_type',
        'published_at',
        'reviewed_at',
        'reviewed_by',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(CommunityPost::class, 'post_id')->withTrashed();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
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

        $this->post?->refreshReplyCount();
    }

    public function reject(User $reviewer, string $reason): void
    {
        $this->status = 'rejected';
        $this->reject_reason = $reason;
        $this->reviewed_at = Carbon::now();
        $this->reviewed_by = $reviewer->id;
        $this->published_at = null;
        $this->save();

        $this->post?->refreshReplyCount();
    }
}
