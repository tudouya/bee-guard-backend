<?php

namespace App\Filament\Admin\Resources\EpidemicMapDatasetResource\Pages;

use App\Filament\Admin\Resources\EpidemicMapDatasetResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEpidemicMapDatasets extends ListRecords
{
    protected static string $resource = EpidemicMapDatasetResource::class;

    protected static ?string $title = '疫情地图数据';

    protected static ?string $breadcrumb = '疫情地图数据';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('新增地图数据'),
        ];
    }
}
