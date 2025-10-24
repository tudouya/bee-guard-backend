<?php

namespace Database\Seeders;

use App\Models\Disease;
use App\Models\EpidemicMapDataset;
use App\Models\EpidemicMapDatasetEntry;
use App\Models\Region;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

class EpidemicMapSampleSeeder extends Seeder
{
    public function run(): void
    {
        $payload = $this->loadPreset();
        $regions = Arr::get($payload, 'regions', []);
        $years = Arr::get($payload, 'years', []);
        $sampleTotal = (int) Arr::get($payload, 'sample_total', 40);
        $diseasesSeries = Arr::get($payload, 'diseases', []);

        if (!Disease::query()->exists()) {
            $this->command->warn('Skip EpidemicMapSampleSeeder: diseases table is empty.');
            return;
        }

        foreach ($regions as $regionMeta) {
            if (!$this->regionExists($regionMeta['region_code'] ?? null, $regionMeta)) {
                $this->command->warn('Skip region: ' . $regionMeta['name'] . '（未在 regions 表中找到）');
                continue;
            }

            foreach ($years as $year) {
                $this->seedForRegionYear($regionMeta, (int) $year, $sampleTotal, $diseasesSeries);
            }
        }
    }

    private function regionExists(?string $code, array $meta): bool
    {
        return Region::query()->where('code', $meta['district_code'])->exists();
    }

    private function seedForRegionYear(array $regionMeta, int $year, int $sampleTotal, array $diseasesSeries): void
    {
        $dataset = EpidemicMapDataset::query()->updateOrCreate(
            [
                'year' => $year,
                'province_code' => $regionMeta['province_code'],
                'district_code' => $regionMeta['district_code'],
                'source_type' => 'manual',
            ],
            [
                'city_code' => $regionMeta['city_code'],
                'source' => '演示数据',
                'notes' => 'Seeder 预置数据，供前端疫情地图联调演示使用',
                'locked' => true,
                'data_updated_at' => CarbonImmutable::now()->subDays(random_int(1, 7)),
            ]
        );

        $dataset->entries()->delete();

        foreach (range(1, 12) as $month) {
            foreach ($this->diseasePayloadForMonth($month, $diseasesSeries) as $diseaseCode => $positive) {
                EpidemicMapDatasetEntry::query()->create([
                    'dataset_id' => $dataset->id,
                    'month' => $month,
                    'disease_code' => $diseaseCode,
                    'positive_cases' => $positive,
                    'sample_total' => $sampleTotal,
                ]);
            }
        }
    }

    private function diseasePayloadForMonth(int $month, array $seriesConfig): array
    {
        $payload = [];
        foreach ($seriesConfig as $code => $series) {
            $index = ($month - 1) % count($series);
            $payload[$code] = Arr::get($series, $index, 0);
        }

        return $payload;
    }

    private function loadPreset(): array
    {
        $path = database_path('data/epidemic-map-sample.json');
        if (!File::exists($path)) {
            return [];
        }

        try {
            $json = File::get($path);
            $payload = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
            return is_array($payload) ? $payload : [];
        } catch (\Throwable $e) {
            $this->command->error('Failed to parse epidemic-map-sample.json: ' . $e->getMessage());
            return [];
        }
    }
}
