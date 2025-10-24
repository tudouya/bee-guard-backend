<?php

namespace App\Filament\Admin\Resources\EpidemicMapDatasetResource\Pages;

use App\Filament\Admin\Resources\EpidemicMapDatasetResource;
use App\Models\Region;
use Filament\Resources\Pages\EditRecord;

class EditEpidemicMapDataset extends EditRecord
{
    protected static string $resource = EpidemicMapDatasetResource::class;

    protected static ?string $title = '编辑疫情地图数据';

    protected static ?string $breadcrumb = '编辑地图数据';

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (!empty($data['district_code']) && empty($data['city_code'])) {
            $region = Region::query()->where('code', $data['district_code'])->first();
            $data['city_code'] = $region?->city_code;
        }

        if (empty($data['data_updated_at'])) {
            $data['data_updated_at'] = now();
        }

        if ($userId = auth()->id()) {
            $data['updated_by'] = $userId;
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
