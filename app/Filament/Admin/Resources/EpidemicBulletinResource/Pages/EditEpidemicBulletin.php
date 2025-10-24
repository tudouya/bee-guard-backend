<?php

namespace App\Filament\Admin\Resources\EpidemicBulletinResource\Pages;

use App\Filament\Admin\Resources\EpidemicBulletinResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEpidemicBulletin extends EditRecord
{
    protected static string $resource = EpidemicBulletinResource::class;

    protected static ?string $title = '编辑疫情通报';

    protected static ?string $breadcrumb = '编辑疫情通报';

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $userId = auth()->id();
        if ($userId) {
            $data['updated_by'] = $userId;
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
