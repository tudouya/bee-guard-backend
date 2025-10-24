<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Epidemic\EpidemicBulletinListRequest;
use App\Http\Resources\Api\EpidemicBulletinDetailResource;
use App\Http\Resources\Api\EpidemicBulletinResource;
use App\Services\Epidemic\EpidemicBulletinService;
use Illuminate\Http\JsonResponse;

class EpidemicBulletinController extends Controller
{
    public function __construct(
        private readonly EpidemicBulletinService $service
    ) {
    }

    public function index(EpidemicBulletinListRequest $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 5);
        $page = (int) $request->input('page', 1);

        $paginator = $this->service->paginatePublished(
            filters: $request->only(['risk_level', 'province_code', 'city_code', 'district_code']),
            perPage: $perPage,
            page: $page
        );

        $transformed = $this->service->transformBulletins($paginator->getCollection());
        $resource = EpidemicBulletinResource::collection($transformed);

        return $resource->additional([
            'success' => true,
            'meta' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ])->response();
    }

    public function show(int $id): JsonResponse
    {
        $bulletin = $this->service->findPublished($id);

        if (!$bulletin) {
            abort(404);
        }

        $transformed = $this->service->transformBulletins(collect([$bulletin]))->first();

        return (new EpidemicBulletinDetailResource($transformed))
            ->additional(['success' => true])
            ->response();
    }
}
