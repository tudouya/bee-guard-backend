<?php

namespace App\Filament\Admin\Resources\CommunityPostReplies\Pages;

use App\Filament\Admin\Resources\CommunityPostReplies\CommunityPostReplyResource;
use Filament\Resources\Pages\ViewRecord;

class ViewCommunityPostReply extends ViewRecord
{
    protected static string $resource = CommunityPostReplyResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function refreshRecord(): void
    {
        $this->record->refresh();
        $this->record->load(['post', 'author', 'reviewer']);
    }
}
