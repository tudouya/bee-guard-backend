<?php

namespace App\Filament\Admin\Resources\CommunityPosts\Pages;

use App\Filament\Admin\Resources\CommunityPosts\CommunityPostResource;
use Filament\Resources\Pages\ViewRecord;

class ViewCommunityPost extends ViewRecord
{
    public array $metadata = [];

    protected static string $resource = CommunityPostResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function mount(int | string $record): void
    {
        parent::mount($record);

        $this->metadata = [
            'category' => $this->record->category,
            'disease_id' => $this->record->disease_id,
        ];
    }

    public function refreshRecord(): void
    {
        $this->record->refresh();
        $this->record->load(['disease', 'author', 'reviewer']);

        $this->metadata = [
            'category' => $this->record->category,
            'disease_id' => $this->record->disease_id,
        ];
    }
}
