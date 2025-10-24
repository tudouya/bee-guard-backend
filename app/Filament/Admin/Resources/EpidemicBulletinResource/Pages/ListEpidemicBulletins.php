<?php

namespace App\Filament\Admin\Resources\EpidemicBulletinResource\Pages;

use App\Filament\Admin\Resources\EpidemicBulletinResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEpidemicBulletins extends ListRecords
{
    protected static string $resource = EpidemicBulletinResource::class;

    protected static ?string $title = '疫情通报';

    protected static ?string $breadcrumb = '疫情通报';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('新增疫情通报'),
        ];
    }
}
