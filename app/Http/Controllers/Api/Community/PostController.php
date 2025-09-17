<?php

namespace App\Http\Controllers\Api\Community;

use App\Enums\RewardMetric;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Community\CommunityPostStoreRequest;
use App\Jobs\EvaluatePostRewardsJob;
use App\Models\CommunityPost;
use App\Models\CommunityPostLike;
use App\Models\Disease;
use App\Models\Upload;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PostController extends Controller
{
    private const CACHE_VIEW_WINDOW = 1800; // 30 分钟

    public function index(Request $request): JsonResponse
    {
        $type = (string) $request->query('type');
        if (!in_array($type, ['question', 'experience'], true)) {
            return response()->json(['code' => 422, 'message' => 'TYPE_INVALID'], 422);
        }

        $perPage = (int) $request->integer('per_page', 10);
        $perPage = $perPage > 50 ? 50 : ($perPage <= 0 ? 10 : $perPage);
        $page = (int) $request->integer('page', 1);
        $sort = (string) $request->query('sort', 'latest');
        $category = trim((string) $request->query('category'));
        $diseaseCode = trim((string) $request->query('disease_code'));

        $query = CommunityPost::query()
            ->with(['author', 'disease'])
            ->where('type', $type)
            ->where('status', 'approved')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());

        if ($category !== '') {
            $query->where('category', $category);
        }

        if ($diseaseCode !== '') {
            $query->whereHas('disease', function ($q) use ($diseaseCode) {
                $q->where('code', $diseaseCode);
            });
        }

        $query->orderBy(
            match ($sort) {
                'hot' => 'likes',
                default => 'published_at',
            },
            'desc'
        );

        /** @var LengthAwarePaginator $paginator */
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $data = collect($paginator->items())->map(function (CommunityPost $post) {
            $author = $post->author;
            $nickname = $author?->nickname ?: $author?->name ?: '蜂友';
            $avatar = $author?->avatar;

            return [
                'id' => $post->id,
                'type' => $post->type,
                'title' => $post->title,
                'excerpt' => Str::limit(strip_tags((string) $post->content), 120),
                'author' => [
                    'id' => $author?->id,
                    'nickname' => $nickname,
                    'avatar' => $avatar,
                ],
                'category' => $post->category,
                'disease' => $post->disease?->only(['id', 'code', 'name']),
                'likes' => (int) $post->likes,
                'views' => (int) $post->views,
                'replies' => (int) $post->replies_count,
                'published_at' => optional($post->published_at)->setTimezone('Asia/Shanghai')?->toDateTimeString(),
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

    public function show(Request $request, int $id): JsonResponse
    {
        $post = CommunityPost::query()
            ->with(['author', 'disease'])
            ->where('id', $id)
            ->where('status', 'approved')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->first();

        if (!$post) {
            return response()->json(['code' => 404, 'message' => 'NOT_FOUND'], 404);
        }

        $this->increaseViews($request, $post);

        $author = $post->author;
        $nickname = $author?->nickname ?: $author?->name ?: '蜂友';
        $avatar = $author?->avatar;
        $images = $this->transformImages($post->images ?? []);

        $liked = false;
        if ($request->user()) {
            $liked = CommunityPostLike::query()
                ->where('post_id', $post->id)
                ->where('user_id', $request->user()->id)
                ->exists();
        }

        return response()->json([
            'code' => 0,
            'message' => 'ok',
            'data' => [
                'id' => $post->id,
                'type' => $post->type,
                'title' => $post->title,
                'content' => $post->content,
                'content_format' => $post->content_format,
                'images' => $images,
                'author' => [
                    'id' => $author?->id,
                    'nickname' => $nickname,
                    'avatar' => $avatar,
                ],
                'category' => $post->category,
                'disease' => $post->disease?->only(['id', 'code', 'name']),
                'likes' => (int) $post->likes,
                'views' => (int) $post->views,
                'replies' => (int) $post->replies_count,
                'published_at' => optional($post->published_at)->setTimezone('Asia/Shanghai')?->toDateTimeString(),
                'liked' => $liked,
            ],
        ]);
    }

    public function store(CommunityPostStoreRequest $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || $user->role !== 'farmer') {
            return response()->json(['code' => 403, 'message' => 'ONLY_FARMER_ALLOWED'], 403);
        }

        $payload = $request->validated();
        $images = $this->syncImages($user->id, $payload['images'] ?? []);

        $diseaseId = null;
        if (!empty($payload['disease_code'])) {
            $disease = Disease::query()
                ->where('code', $payload['disease_code'])
                ->where('status', 'active')
                ->first();
            if (!$disease) {
                return response()->json(['code' => 422, 'message' => 'DISEASE_NOT_FOUND'], 422);
            }
            $diseaseId = $disease->id;
        }

        $post = CommunityPost::query()->create([
            'user_id' => $user->id,
            'type' => $payload['type'],
            'title' => trim($payload['title']),
            'content' => Str::of($payload['content'])->stripTags()->trim()->toString(),
            'content_format' => 'plain',
            'images' => $images,
            'disease_id' => $diseaseId,
            'category' => $payload['category'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json([
            'code' => 0,
            'message' => 'ok',
            'data' => [
                'id' => $post->id,
                'status' => $post->status,
            ],
        ], 201);
    }

    public function mine(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['code' => 401, 'message' => 'UNAUTHENTICATED'], 401);
        }

        $perPage = (int) $request->integer('per_page', 10);
        $perPage = $perPage > 50 ? 50 : ($perPage <= 0 ? 10 : $perPage);
        $page = (int) $request->integer('page', 1);
        $status = trim((string) $request->query('status'));

        $query = CommunityPost::query()
            ->with('disease')
            ->where('user_id', $user->id)
            ->orderByDesc('created_at');

        if ($status !== '') {
            $query->where('status', $status);
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $data = collect($paginator->items())->map(function (CommunityPost $post) {
            return [
                'id' => $post->id,
                'type' => $post->type,
                'title' => $post->title,
                'status' => $post->status,
                'reject_reason' => $post->reject_reason,
                'likes' => (int) $post->likes,
                'views' => (int) $post->views,
                'replies' => (int) $post->replies_count,
                'published_at' => optional($post->published_at)->setTimezone('Asia/Shanghai')?->toDateTimeString(),
                'created_at' => optional($post->created_at)->setTimezone('Asia/Shanghai')?->toDateTimeString(),
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

    private function transformImages(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (empty($ids)) {
            return [];
        }

        $uploads = Upload::query()->whereIn('id', $ids)->get();
        $map = $uploads->mapWithKeys(function (Upload $u) {
            return [$u->id => Storage::disk($u->disk)->url($u->path)];
        });

        return collect($ids)
            ->filter(fn ($id) => $map->has($id))
            ->map(fn ($id) => ['id' => $id, 'url' => $map->get($id)])
            ->values()
            ->all();
    }

    private function syncImages(int $userId, array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $ids = array_slice(array_values(array_unique(array_map('intval', $ids))), 0, 3);
        if (empty($ids)) {
            return [];
        }

        $count = Upload::query()
            ->whereIn('id', $ids)
            ->where(function ($q) use ($userId) {
                $q->whereNull('user_id')->orWhere('user_id', $userId);
            })
            ->count();

        if ($count !== count($ids)) {
            throw ValidationException::withMessages([
                'images' => ['图片文件无效或已失效'],
            ]);
        }

        return $ids;
    }

    private function increaseViews(Request $request, CommunityPost $post): void
    {
        $fingerprint = $this->buildFingerprint($request);
        $key = 'community:post:view:' . $post->id . ':' . $fingerprint;
        if (Cache::add($key, 1, self::CACHE_VIEW_WINDOW)) {
            CommunityPost::query()->where('id', $post->id)->increment('views');
            $post->views++;
            EvaluatePostRewardsJob::dispatch($post->id, [RewardMetric::Views->value]);
        }
    }

    private function buildFingerprint(Request $request): string
    {
        $user = $request->user();
        if ($user) {
            return 'u:' . $user->id;
        }

        $deviceId = (string) $request->input('deviceId');
        if ($deviceId !== '') {
            return 'd:' . substr($deviceId, 0, 64);
        }

        return 'g:' . substr(sha1($request->ip() . '|' . (string) $request->userAgent()), 0, 32);
    }
}
