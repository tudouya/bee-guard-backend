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
    public function buildPieDataset(string $provinceCode, string $districtCode, int $year, ?int $compareYear = null, ?int $month = null): array
    {
        $compareYear ??= $year - 1;
        $monthFilter = $month !== null ? max(1, min(12, $month)) : null;

        $legend = $this->buildLegend();
        $legendByCode = $legend->keyBy('code');

        $currentDatasets = $this->findDatasetsBySource($provinceCode, $districtCode, $year);
        $previousDatasets = $compareYear
            ? $this->findDatasetsBySource($provinceCode, $districtCode, $compareYear)
            : ['manual' => null, 'auto' => null];

        $currentEntries = $this->mergeDatasetEntries($currentDatasets['manual'], $currentDatasets['auto']);
        $previousEntries = $this->mergeDatasetEntries($previousDatasets['manual'], $previousDatasets['auto']);

        $groups = collect([
            $this->buildGroupPayload('current', '今年病害流行图', $year, $currentEntries, $legendByCode, $monthFilter),
            $this->buildGroupPayload('previous', '往年病害流行图', $compareYear, $previousEntries, $legendByCode, $monthFilter),
        ])->filter()->values();

        $names = $this->regions->mapCodesToNames($provinceCode, null, $districtCode);
        $provinceName = $names['province'] ?? null;
        $districtName = $names['district'] ?? null;

        $updatedAt = collect([
            $currentDatasets['manual']?->data_updated_at,
            $currentDatasets['manual']?->updated_at,
            $currentDatasets['auto']?->data_updated_at,
            $currentDatasets['auto']?->updated_at,
            $previousDatasets['manual']?->data_updated_at,
            $previousDatasets['manual']?->updated_at,
            $previousDatasets['auto']?->data_updated_at,
            $previousDatasets['auto']?->updated_at,
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
            'availableMonths' => $this->resolveAvailableMonthsFromCollections($currentEntries, $previousEntries),
            'updatedAt' => $updatedAt?->toDateTimeString(),
        ];
    }

    private function findDatasetsBySource(string $provinceCode, string $districtCode, int $year): array
    {
        if ($year < 0) {
            return [
                'manual' => null,
                'auto' => null,
            ];
        }

        $sources = ['manual', 'auto'];

        $datasets = [];
        foreach ($sources as $source) {
            $datasets[$source] = EpidemicMapDataset::query()
                ->with(['entries' => function ($query) {
                    $query->orderBy('month')->orderBy('disease_code');
                }])
                ->where('year', $year)
                ->where('province_code', $provinceCode)
                ->where('district_code', $districtCode)
                ->where('source_type', $source)
                ->orderByDesc('locked')
                ->orderByDesc('data_updated_at')
                ->first();
        }

        return $datasets + ['manual' => null, 'auto' => null];
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

    private function buildGroupPayload(string $key, string $title, ?int $year, Collection $entriesByMonth, Collection $legendByCode, ?int $filterMonth = null): ?array
    {
        if (!$year) {
            return null;
        }

        $entriesByMonth = $entriesByMonth ?? collect();

        $months = [];
        $targetMonths = $filterMonth ? [$filterMonth] : range(1, 12);
        foreach ($targetMonths as $month) {
            $monthEntries = $entriesByMonth->get($month, collect());
            if (!($monthEntries instanceof Collection)) {
                $monthEntries = collect($monthEntries);
            }
            $hasData = $monthEntries->isNotEmpty();

            $slices = $monthEntries->map(function (array $entry) use ($legendByCode) {
                $diseaseCode = $entry['disease_code'];
                $meta = $legendByCode->get($diseaseCode, [
                    'code' => $diseaseCode,
                    'key' => Str::lower($diseaseCode),
                    'label' => $diseaseCode,
                    'name' => $diseaseCode,
                    'color' => '#888888',
                ]);

                return [
                    'diseaseCode' => $diseaseCode,
                    'key' => $meta['key'] ?? Str::lower($diseaseCode),
                    'label' => $meta['label'] ?? $diseaseCode,
                    'name' => $meta['name'] ?? $diseaseCode,
                    'color' => $meta['color'] ?? '#888888',
                    'positive' => $entry['positive'],
                    'samples' => $entry['samples'],
                    'rate' => $entry['rate'],
                    'sources' => $entry['sources'] ?? [],
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

    /**
     * @return array<int>
     */
    private function resolveAvailableMonthsFromCollections(Collection ...$collections): array
    {
        return collect($collections)
            ->filter()
            ->flatMap(function (Collection $collection) {
                return $collection->keys();
            })
            ->filter()
            ->map(fn ($month) => (int) $month)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function mergeDatasetEntries(?EpidemicMapDataset $manual, ?EpidemicMapDataset $auto): Collection
    {
        $result = collect();

        $accumulate = function (?EpidemicMapDataset $dataset, string $source) use (&$result) {
            if (!$dataset) {
                return;
            }

            $dataset->entries->each(function ($entry) use (&$result, $source) {
                $month = (int) $entry->month;
                $diseaseCode = $entry->disease_code;

                $monthBucket = $result->get($month, collect());
                if (!($monthBucket instanceof Collection)) {
                    $monthBucket = collect($monthBucket);
                }

                $current = $monthBucket->get($diseaseCode, [
                    'disease_code' => $diseaseCode,
                    'positive' => 0,
                    'samples' => 0,
                    'sources' => [],
                ]);

                $current['positive'] += (int) $entry->positive_cases;
                $current['samples'] += (int) $entry->sample_total;
                $current['sources'][$source] = true;

                $monthBucket->put($diseaseCode, $current);
                $result->put($month, $monthBucket);
            });
        };

        $accumulate($manual, 'manual');
        $accumulate($auto, 'auto');

        return $result->map(function (Collection $diseaseCollection) {
            return $diseaseCollection->map(function (array $item) {
                $samples = max(0, (int) $item['samples']);
                $positive = max(0, (int) $item['positive']);
                $rate = $samples > 0 ? round($positive / $samples, 6) : 0.0;

                return [
                    'disease_code' => $item['disease_code'],
                    'positive' => $positive,
                    'samples' => $samples,
                    'rate' => $rate,
                    'sources' => array_keys($item['sources'] ?? []),
                ];
            });
        });
    }
}
