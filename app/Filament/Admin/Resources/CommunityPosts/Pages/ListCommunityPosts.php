<?php

namespace App\Filament\Admin\Resources\CommunityPosts\Pages;

use App\Filament\Admin\Resources\CommunityPosts\CommunityPostResource;
use App\Models\CommunityPost;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListCommunityPosts extends ListRecords
{
    protected static string $resource = CommunityPostResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            'pending' => Tab::make('待审核')
                ->badge(fn () => $this->getStatusCount('pending'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending')),
            'all' => Tab::make('全部'),
            'approved' => Tab::make('已通过')
                ->badge(fn () => $this->getStatusCount('approved'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'approved')),
            'rejected' => Tab::make('已驳回')
                ->badge(fn () => $this->getStatusCount('rejected'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'rejected')),
        ];
    }

    public function getDefaultActiveTab(): string
    {
        return 'pending';
    }

    private function getStatusCount(string $status): int
    {
        return CommunityPost::query()
            ->where('status', $status)
            ->count();
    }
}
