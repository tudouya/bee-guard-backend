<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Epidemic\EpidemicMapPieRequest;
use App\Services\Epidemic\EpidemicMapService;
use Illuminate\Http\JsonResponse;

class EpidemicMapController extends Controller
{
    public function __construct(
        private readonly EpidemicMapService $service
    ) {
    }

    public function pie(EpidemicMapPieRequest $request): JsonResponse
    {
        $provinceCode = $request->string('province_code')->toString();
        $districtCode = $request->string('district_code')->toString();
        $year = $request->input('year', (int) now()->year);
        $compareYear = $request->input('compare_year');
        $month = $request->input('month');

        $payload = $this->service->buildPieDataset(
            $provinceCode,
            $districtCode,
            (int) $year,
            $compareYear !== null ? (int) $compareYear : null,
            $month !== null ? (int) $month : null
        );

        return response()->json([
            'data' => $payload,
        ]);
    }
}
