<?php

namespace App\Services\Epidemic;

use App\Models\Disease;
use App\Models\EpidemicMapDataset;
use App\Repositories\RegionRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class EpidemicMapService
{
    private const DEFAULT_COLORS = [
        '#F05A5A',
        '#65C18C',
        '#F9C74F',
        '#42A5F5',
        '#8E6CFF',
        '#F28482',
        '#6BCB77',
        '#FF9F1C',
    ];

    public function __construct(
        private readonly RegionRepository $regions
    ) {
    }

    /**
     * 构建疫情地图饼图数据
     */
    public function buildPieDataset(string $provinceCode, string $districtCode, int $year, ?int $compareYear = null): array
    {
        $compareYear ??= $year - 1;

        $legend = $this->buildLegend();
        $legendByCode = $legend->keyBy('code');

        $currentDataset = $this->findDataset($provinceCode, $districtCode, $year);
        $previousDataset = $compareYear ? $this->findDataset($provinceCode, $districtCode, $compareYear) : null;

        $groups = collect([
            $this->buildGroupPayload('current', '今年病害流行图', $year, $currentDataset, $legendByCode),
            $this->buildGroupPayload('previous', '往年病害流行图', $compareYear, $previousDataset, $legendByCode),
        ])->filter()->values();

        $names = $this->regions->mapCodesToNames($provinceCode, null, $districtCode);
        $provinceName = $names['province'] ?? null;
        $districtName = $names['district'] ?? null;

        $updatedAt = collect([
            $currentDataset?->data_updated_at,
            $currentDataset?->updated_at,
            $previousDataset?->data_updated_at,
            $previousDataset?->updated_at,
        ])->filter()->max();

        return [
            'region' => [
                'provinceCode' => $provinceCode,
                'provinceName' => $provinceName,
                'districtCode' => $districtCode,
                'districtName' => $districtName,
            ],
            'legend' => $legend->values()->all(),
            'groups' => $groups->all(),
            'updatedAt' => $updatedAt?->toDateTimeString(),
        ];
    }

    private function findDataset(string $provinceCode, string $districtCode, int $year): ?EpidemicMapDataset
    {
        if ($year < 0) {
            return null;
        }

        return EpidemicMapDataset::query()
            ->with(['entries' => function ($query) {
                $query->orderBy('month')->orderBy('disease_code');
            }])
            ->where('year', $year)
            ->where('province_code', $provinceCode)
            ->where('district_code', $districtCode)
            ->orderByDesc('locked')
            ->orderByRaw("CASE WHEN source_type = 'manual' THEN 0 ELSE 1 END")
            ->orderByDesc('data_updated_at')
            ->first();
    }

    private function buildLegend(): Collection
    {
        $defaultColors = self::DEFAULT_COLORS;

        return Disease::query()
            ->where('status', '!=', 'hidden')
            ->orderBy('map_order')
            ->orderBy('sort')
            ->orderBy('id')
            ->get(['code', 'name', 'map_alias', 'map_color'])
            ->values()
            ->map(function (Disease $disease, int $index) use ($defaultColors) {
                $color = $disease->map_color ?: ($defaultColors[$index % count($defaultColors)] ?? '#888888');

                return [
                    'code' => $disease->code,
                    'key' => Str::lower($disease->code),
                    'label' => $disease->code,
                    'name' => $disease->map_alias ?: $disease->name,
                    'color' => $color,
                ];
            });
    }

    private function buildGroupPayload(string $key, string $title, ?int $year, ?EpidemicMapDataset $dataset, Collection $legendByCode): ?array
    {
        if (!$year) {
            return null;
        }

        $entriesByMonth = $dataset?->entries->groupBy('month') ?? collect();

        $months = [];
        for ($month = 1; $month <= 12; $month++) {
            $monthEntries = $entriesByMonth->get($month, collect());
            $hasData = $monthEntries->isNotEmpty();

            $slices = $monthEntries->map(function ($entry) use ($legendByCode) {
                $meta = $legendByCode->get($entry->disease_code, [
                    'code' => $entry->disease_code,
                    'key' => Str::lower($entry->disease_code),
                    'label' => $entry->disease_code,
                    'name' => $entry->disease_code,
                    'color' => '#888888',
                ]);

                return [
                    'diseaseCode' => $entry->disease_code,
                    'key' => $meta['key'] ?? Str::lower($entry->disease_code),
                    'label' => $meta['label'] ?? $entry->disease_code,
                    'name' => $meta['name'] ?? $entry->disease_code,
                    'color' => $meta['color'] ?? '#888888',
                    'positive' => $entry->positive_cases,
                    'samples' => $entry->sample_total,
                    'rate' => $entry->rate,
                ];
            })->values()->all();

            $months[] = [
                'chartId' => sprintf('chart-%s-%d', $key, $month - 1),
                'monthLabel' => sprintf('%02d月', $month),
                'monthValue' => $month,
                'hasData' => $hasData,
                'slices' => $hasData ? $slices : [],
            ];
        }

        return [
            'key' => $key,
            'title' => $title,
            'year' => $year,
            'months' => $months,
        ];
    }
}
