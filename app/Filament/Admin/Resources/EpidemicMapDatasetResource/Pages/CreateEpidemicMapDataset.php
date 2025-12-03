<?php

namespace App\Filament\Admin\Resources\EpidemicMapDatasetResource\Pages;

use App\Filament\Admin\Resources\EpidemicMapDatasetResource;
use App\Models\EpidemicMapDataset;
use App\Models\Region;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateEpidemicMapDataset extends CreateRecord
{
    protected static string $resource = EpidemicMapDatasetResource::class;

    protected static ?string $title = '新增疫情地图数据';

    protected static ?string $breadcrumb = '新增地图数据';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['source_type'] = $data['source_type'] ?? 'manual';

        if (!empty($data['district_code']) && empty($data['city_code'])) {
            $region = Region::query()->where('code', $data['district_code'])->first();
            $data['city_code'] = $region?->city_code;
        }

        if (empty($data['data_updated_at'])) {
            $data['data_updated_at'] = now();
        }

        if ($userId = auth()->id()) {
            $data['created_by'] = $userId;
            $data['updated_by'] = $userId;
        }

        $this->validateEntriesUniqueness($data);
        $this->ensureUniqueDataset($data);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    /**
     * Ensure the year/region/source_type combination is unique before inserting.
     */
    private function ensureUniqueDataset(array $data): void
    {
        $exists = EpidemicMapDataset::query()
            ->where('year', $data['year'] ?? null)
            ->where('province_code', $data['province_code'] ?? null)
            ->where('district_code', $data['district_code'] ?? null)
            ->where('source_type', $data['source_type'] ?? 'manual')
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'district_code' => '同一年份、地区与数据类型的疫情地图数据已存在，请前往列表页面进行编辑。',
            ]);
        }
    }

    /**
     * 校验月度病害数据中“月份+病种”组合的唯一性，定位到具体重复行。
     */
    private function validateEntriesUniqueness(array $data): void
    {
        $entries = $data['entries'] ?? [];
        if (!is_array($entries) || count($entries) < 2) {
            return;
        }

        $groups = [];
        foreach ($entries as $index => $entry) {
            $month = (int) ($entry['month'] ?? 0);
            $disease = $entry['disease_code'] ?? null;
            if (!$month || !$disease) {
                continue;
            }

            $key = sprintf('%02d-%s', $month, $disease);
            $groups[$key] = $groups[$key] ?? [];
            $groups[$key][] = (int) $index;
        }

        $errors = [];
        $duplicates = [];

        foreach ($groups as $key => $indexes) {
            if (count($indexes) < 2) {
                continue;
            }

            [$monthLabel, $diseaseCode] = explode('-', $key, 2);
            $label = sprintf('%s月 - %s', $monthLabel, $diseaseCode);
            $duplicates[] = $label;

            $rowNumbers = array_map(fn (int $i) => $i + 1, $indexes);
            foreach ($indexes as $i) {
                $otherRows = array_diff($rowNumbers, [$i + 1]);
                $errors["entries.$i.disease_code"][] = sprintf(
                    '%s 与第 %s 条重复，请修改或删除重复项',
                    $label,
                    implode('、', $otherRows)
                );
            }
        }

        if (!empty($errors)) {
            $errors['entries'] = [
                '发现重复组合：' . implode('，', array_unique($duplicates)),
            ];

            throw ValidationException::withMessages($errors);
        }
    }
}
