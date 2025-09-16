<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class KnowledgeArticle extends Model
{
    use HasFactory;

    protected $fillable = [
        'disease_id',
        'title',
        'brief',
        'body_html',
        'published_at',
        'views',
        'created_by',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function disease()
    {
        return $this->belongsTo(Disease::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    // 基础清洗：移除 script/style、事件属性与 javascript: 协议（简化版）
    public function setBodyHtmlAttribute($value)
    {
        $clean = (string) $value;
        // 移除 <script> 与 <style>
        $clean = preg_replace('#<\s*(script|style)[^>]*>.*?<\s*/\s*\1>#is', '', $clean) ?? '';
        // 移除 on* 事件属性
        $clean = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean) ?? '';
        // 清理 href/src 中的 javascript: 协议
        $clean = preg_replace('/\s(href|src)\s*=\s*("|\')(javascript:.*?)(\2)/i', ' $1="#"', $clean) ?? '';
        $this->attributes['body_html'] = $clean;
    }
}

