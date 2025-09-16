<?php

namespace App\Filament\Admin\Resources\CommunityPosts\Pages;

use App\Filament\Admin\Resources\CommunityPosts\CommunityPostResource;
use Filament\Resources\Pages\ListRecords;

class ListCommunityPosts extends ListRecords
{
    protected static string $resource = CommunityPostResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
