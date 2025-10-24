<?php

namespace App\Services\Epidemic;

use App\Models\EpidemicBulletin;
use App\Repositories\RegionRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class EpidemicBulletinService
{
    public function __construct(
        private readonly RegionRepository $regions
    ) {
    }

    public function paginatePublished(array $filters = [], int $perPage = 10, int $page = 1): LengthAwarePaginator
    {
        $query = EpidemicBulletin::query()
            ->where('status', EpidemicBulletin::STATUS_PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderByDesc('published_at')
            ->orderByDesc('id');

        if ($risk = Arr::get($filters, 'risk_level')) {
            $query->where('risk_level', $risk);
        }

        if ($province = Arr::get($filters, 'province_code')) {
            $query->where('province_code', $province);
        }
        if ($city = Arr::get($filters, 'city_code')) {
            $query->where('city_code', $city);
        }
        if ($district = Arr::get($filters, 'district_code')) {
            $query->where('district_code', $district);
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function findPublished(int $id): ?EpidemicBulletin
    {
        return EpidemicBulletin::query()
            ->where('status', EpidemicBulletin::STATUS_PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->find($id);
    }

    public function transformBulletins(Collection $collection): Collection
    {
        return $collection->map(function (EpidemicBulletin $bulletin) {
            $region = $this->regions->mapCodesToNames(
                $bulletin->province_code,
                $bulletin->city_code,
                $bulletin->district_code
            );

            return [
                'id' => $bulletin->id,
                'title' => $bulletin->title,
                'summary' => $bulletin->summary,
                'content' => $bulletin->content,
                'risk_level' => $bulletin->risk_level,
                'risk_level_text' => $this->riskLevelText($bulletin->risk_level),
                'region' => [
                    'provinceCode' => $bulletin->province_code,
                    'provinceName' => $region['province'],
                    'cityCode' => $bulletin->city_code,
                    'cityName' => $region['city'],
                    'districtCode' => $bulletin->district_code,
                    'districtName' => $region['district'],
                ],
                'published_at' => $bulletin->published_at,
                'source' => $bulletin->source,
                'attachments' => $bulletin->attachments ?? [],
            ];
        });
    }

    private function riskLevelText(string $level): string
    {
        return match ($level) {
            EpidemicBulletin::RISK_HIGH => '高风险',
            EpidemicBulletin::RISK_MEDIUM => '中风险',
            default => '低风险',
        };
    }
}
