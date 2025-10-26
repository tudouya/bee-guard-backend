<?php

namespace App\Filament\Admin\Resources\EpidemicMapDatasetResource\Pages;

use App\Filament\Admin\Resources\EpidemicMapDatasetResource;
use App\Models\EpidemicMapDataset;
use App\Models\Region;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditEpidemicMapDataset extends EditRecord
{
    protected static string $resource = EpidemicMapDatasetResource::class;

    protected static ?string $title = '编辑疫情地图数据';

    protected static ?string $breadcrumb = '编辑地图数据';

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['source_type'] = $data['source_type'] ?? $this->record->source_type;

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

        $this->ensureUniqueDataset($data, $this->record->getKey());

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    /**
     * Ensure the year/region/source_type combination stays unique while editing.
     */
    private function ensureUniqueDataset(array $data, ?int $ignoreId = null): void
    {
        $exists = EpidemicMapDataset::query()
            ->where('year', $data['year'] ?? null)
            ->where('province_code', $data['province_code'] ?? null)
            ->where('district_code', $data['district_code'] ?? null)
            ->where('source_type', $data['source_type'] ?? 'manual')
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'district_code' => '同一年份、地区与数据类型的疫情地图数据已存在，请调整后再保存。',
            ]);
        }
    }
}
