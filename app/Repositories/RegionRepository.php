<?php

namespace App\Repositories;

use App\Models\Region;
use Illuminate\Support\Collection;

class RegionRepository
{
    public function getProvinces(): Collection
    {
        return Region::query()
            ->whereColumn('province_code', 'code')
            ->orderBy('code')
            ->get();
    }

    public function getCities(string $provinceCode): Collection
    {
        return Region::query()
            ->where('province_code', $provinceCode)
            ->whereColumn('city_code', 'code')
            ->orderBy('code')
            ->get();
    }

    public function getDistricts(string $cityCode): Collection
    {
        return Region::query()
            ->where('city_code', $cityCode)
            ->where('code', '!=', $cityCode)
            ->orderBy('code')
            ->get();
    }

    public function findByCode(string $code): ?Region
    {
        return Region::query()->where('code', $code)->first();
    }

    public function mapCodesToNames(?string $provinceCode, ?string $cityCode, ?string $districtCode): array
    {
        $names = [
            'province' => null,
            'city' => null,
            'district' => null,
        ];

        if ($provinceCode) {
            $province = $this->findByCode($provinceCode);
            $names['province'] = $province?->name;
        }

        if ($cityCode) {
            $city = $this->findByCode($cityCode);
            $names['city'] = $city?->name;
        }

        if ($districtCode) {
            $district = $this->findByCode($districtCode);
            $names['district'] = $district?->name;
        }

        return $names;
    }

    public function searchByName(string $keyword, int $limit = 20): Collection
    {
        return Region::query()
            ->where(function ($query) use ($keyword) {
                $query->where('name', 'like', '%' . $keyword . '%');
            })
            ->orderByRaw('LENGTH(code)')
            ->orderBy('code')
            ->limit($limit)
            ->get();
    }
}
