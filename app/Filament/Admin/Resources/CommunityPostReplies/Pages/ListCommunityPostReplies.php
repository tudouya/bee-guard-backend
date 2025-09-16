<?php

namespace App\Filament\Admin\Resources\CommunityPostReplies\Pages;

use App\Filament\Admin\Resources\CommunityPostReplies\CommunityPostReplyResource;
use Filament\Resources\Pages\ListRecords;

class ListCommunityPostReplies extends ListRecords
{
    protected static string $resource = CommunityPostReplyResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
