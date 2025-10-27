<?php

namespace App\Services\Epidemic;

use App\Models\EpidemicBulletin;
use App\Repositories\RegionRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

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

    public function latestHomepageFeatured(int $limit = 4): Collection
    {
        return EpidemicBulletin::query()
            ->where('status', EpidemicBulletin::STATUS_PUBLISHED)
            ->where('homepage_featured', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
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
                'thumbnail_url' => $this->resolveImageUrl($bulletin->thumbnail_url),
                'homepage_featured' => (bool) $bulletin->homepage_featured,
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

    private function resolveImageUrl(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $value)) {
            return $value;
        }

        $disk = config('filament.default_filesystem_disk') ?: config('filesystems.default', 'public');

        try {
            return Storage::disk($disk)->url($value);
        } catch (\Throwable $e) {
            try {
                return Storage::disk('public')->url($value);
            } catch (\Throwable $e2) {
                return null;
            }
        }
    }
}
