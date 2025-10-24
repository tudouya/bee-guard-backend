<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\RegionRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class RegionController extends Controller
{
    public function __construct(
        private readonly RegionRepository $regions
    ) {
    }

    public function provinces(): JsonResponse
    {
        $items = Cache::rememberForever('regions:provinces', function () {
            return $this->regions->getProvinces()
                ->map(fn ($region) => [
                    'code' => $region->code,
                    'name' => $region->name,
                ])
                ->values();
        });

        return response()->json(['data' => $items]);
    }

    public function cities(string $provinceCode): JsonResponse
    {
        if (!$this->validCode($provinceCode)) {
            return response()->json(['data' => []]);
        }

        $cacheKey = 'regions:cities:' . $provinceCode;

        $items = Cache::rememberForever($cacheKey, function () use ($provinceCode) {
            return $this->regions->getCities($provinceCode)
                ->map(fn ($region) => [
                    'code' => $region->code,
                    'name' => $region->name,
                ])
                ->values();
        });

        return response()->json(['data' => $items]);
    }

    public function districts(string $cityCode): JsonResponse
    {
        if (!$this->validCode($cityCode)) {
            return response()->json(['data' => []]);
        }

        $cacheKey = 'regions:districts:' . $cityCode;

        $items = Cache::rememberForever($cacheKey, function () use ($cityCode) {
            return $this->regions->getDistricts($cityCode)
                ->map(fn ($region) => [
                    'code' => $region->code,
                    'name' => $region->name,
                ])
                ->values();
        });

        return response()->json(['data' => $items]);
    }

    private function validCode(string $code): bool
    {
        return (bool) preg_match('/^\d{2,12}$/', $code);
    }
}
