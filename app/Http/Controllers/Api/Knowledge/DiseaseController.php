<?php

namespace App\Http\Controllers\Api\Knowledge;

use App\Http\Controllers\Controller;
use App\Models\Disease;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class DiseaseController extends Controller
{
    // GET /api/knowledge/diseases
    public function index(Request $request)
    {
        $perPage = (int) $request->integer('per_page', 25);
        $perPage = $perPage > 50 ? 50 : ($perPage <= 0 ? 10 : $perPage);
        $page = (int) $request->integer('page', 1);

        $query = Disease::query()
            ->where('status', 'active')
            ->orderBy('sort')
            ->withCount(['knowledgeArticles as article_count' => function ($q) {
                $q->whereNotNull('published_at')->where('published_at', '<=', now());
            }]);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $data = collect($paginator->items())->map(function (Disease $d) {
            return [
                'code' => $d->code,
                'name' => $d->name,
                'brief' => $d->brief,
                'symptom' => $d->symptom,
                'articleCount' => (int) ($d->article_count ?? 0),
            ];
        })->values();

        return response()->json([
            'code' => 0,
            'message' => 'ok',
            'data' => $data,
            'meta' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'total_pages' => $paginator->lastPage(),
            ],
        ]);
    }

    // GET /api/knowledge/diseases/{code}
    public function show(Request $request, string $code)
    {
        $d = Disease::query()
            ->where('code', $code)
            ->where('status', 'active')
            ->withCount(['knowledgeArticles as article_count' => function ($q) {
                $q->whereNotNull('published_at')->where('published_at', '<=', now());
            }])
            ->first();

        if (!$d) {
            return response()->json(['code' => 404, 'message' => 'NOT_FOUND'], 404);
        }

        return response()->json([
            'code' => 0,
            'message' => 'ok',
            'data' => [
                'code' => $d->code,
                'name' => $d->name,
                'description' => $d->description,
                'symptom' => $d->symptom,
                'transmit' => $d->transmit,
                'prevention' => $d->prevention,
                'articleCount' => (int) ($d->article_count ?? 0),
            ],
        ]);
    }

    // GET /api/knowledge/diseases/{code}/articles
    public function articles(Request $request, string $code)
    {
        $perPage = (int) $request->integer('per_page', 25);
        $perPage = $perPage > 50 ? 50 : ($perPage <= 0 ? 10 : $perPage);
        $page = (int) $request->integer('page', 1);

        $d = Disease::query()->where('code', $code)->where('status', 'active')->first();
        if (!$d) {
            return response()->json(['code' => 404, 'message' => 'NOT_FOUND'], 404);
        }

        $query = $d->knowledgeArticles()->published()->orderByDesc('published_at');
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        $data = collect($paginator->items())->map(function ($a) {
            return [
                'id' => $a->id,
                'title' => $a->title,
                'brief' => $a->brief,
                'date' => optional($a->published_at)->setTimezone('Asia/Shanghai')?->format('Y-m-d'),
                'views' => (int) $a->views,
            ];
        })->values();

        return response()->json([
            'code' => 0,
            'message' => 'ok',
            'data' => $data,
            'meta' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'total_pages' => $paginator->lastPage(),
            ],
        ]);
    }
}
