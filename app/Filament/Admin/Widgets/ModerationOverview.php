<?php

namespace App\Filament\Admin\Widgets;

use App\Models\CommunityPost;
use App\Models\CommunityPostReply;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class ModerationOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $now = Carbon::now();
        $since = $now->clone()->subDay();

        $pendingPosts = CommunityPost::query()
            ->where('status', 'pending')
            ->count();

        $newPosts = CommunityPost::query()
            ->where('created_at', '>=', $since)
            ->count();

        $pendingReplies = CommunityPostReply::query()
            ->where('status', 'pending')
            ->count();

        $newReplies = CommunityPostReply::query()
            ->where('created_at', '>=', $since)
            ->count();

        $postUrl = route('filament.admin.resources.community-posts.index', [
            'tab' => 'pending',
        ]);

        $replyUrl = route('filament.admin.resources.community-post-replies.index', [
            'tab' => 'pending',
        ]);

        return [
            Stat::make('待审核帖子', $pendingPosts)
                ->description(sprintf('更新于 %s · 24h 新增 %d 条', $now->format('Y-m-d H:i'), $newPosts))
                ->descriptionIcon('heroicon-o-arrow-trending-up')
                ->color($pendingPosts > 0 ? 'warning' : 'success')
                ->url($postUrl)
                ->extraAttributes([
                    'class' => 'cursor-pointer',
                ]),
            Stat::make('待审核回复', $pendingReplies)
                ->description(sprintf('更新于 %s · 24h 新增 %d 条', $now->format('Y-m-d H:i'), $newReplies))
                ->descriptionIcon('heroicon-o-arrow-trending-up')
                ->color($pendingReplies > 0 ? 'warning' : 'success')
                ->url($replyUrl)
                ->extraAttributes([
                    'class' => 'cursor-pointer',
                ]),
        ];
    }
}
