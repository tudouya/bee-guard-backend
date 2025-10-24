<?php

namespace App\Filament\Admin\Resources\EpidemicBulletinResource\Pages;

use App\Filament\Admin\Resources\EpidemicBulletinResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEpidemicBulletin extends CreateRecord
{
    protected static string $resource = EpidemicBulletinResource::class;

    protected static ?string $title = '新增疫情通报';

    protected static ?string $breadcrumb = '新增疫情通报';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $userId = auth()->id();
        if ($userId) {
            $data['created_by'] = $userId;
            $data['updated_by'] = $userId;
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
