<?php

namespace App\Http\Controllers\Admin\Community;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Community\RejectPostRequest;
use App\Models\CommunityPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensureAdmin($user);

        $perPage = (int) $request->integer('per_page', 15);
        $perPage = $perPage > 100 ? 100 : ($perPage <= 0 ? 15 : $perPage);
        $page = (int) $request->integer('page', 1);

        $query = CommunityPost::query()->with(['author', 'disease']);

        $type = (string) $request->query('type');
        if ($type !== '' && in_array($type, ['question', 'experience'], true)) {
            $query->where('type', $type);
        }

        $status = (string) $request->query('status');
        if ($status !== '') {
            $query->where('status', $status);
        }

        $keyword = trim((string) $request->query('keyword'));
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('title', 'like', "%{$keyword}%")
                    ->orWhere('content', 'like', "%{$keyword}%");
            });
        }

        $category = trim((string) $request->query('category'));
        if ($category !== '') {
            $query->where('category', $category);
        }

        $diseaseCode = trim((string) $request->query('disease_code'));
        if ($diseaseCode !== '') {
            $query->whereHas('disease', function ($q) use ($diseaseCode) {
                $q->where('code', $diseaseCode);
            });
        }

        $owner = trim((string) $request->query('author'));
        if ($owner !== '') {
            $query->whereHas('author', function ($q) use ($owner) {
                $q->where('name', 'like', "%{$owner}%")
                    ->orWhere('nickname', 'like', "%{$owner}%")
                    ->orWhere('username', 'like', "%{$owner}%");
            });
        }

        $trashed = (string) $request->query('trashed');
        if ($trashed === 'only') {
            $query->onlyTrashed();
        } elseif ($trashed === 'with') {
            $query->withTrashed();
        }

        $query->orderByDesc('created_at');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $data = collect($paginator->items())->map(function (CommunityPost $post) {
            $author = $post->author;
            return [
                'id' => $post->id,
                'type' => $post->type,
                'title' => $post->title,
                'status' => $post->status,
                'category' => $post->category,
                'disease' => $post->disease?->only(['id', 'code', 'name']),
                'likes' => (int) $post->likes,
                'views' => (int) $post->views,
                'replies' => (int) $post->replies_count,
                'author' => $author ? [
                    'id' => $author->id,
                    'name' => $author->display_name ?? ($author->name ?: $author->nickname),
                    'role' => $author->role,
                ] : null,
                'published_at' => optional($post->published_at)->toDateTimeString(),
                'created_at' => optional($post->created_at)->toDateTimeString(),
                'deleted_at' => optional($post->deleted_at)->toDateTimeString(),
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
        $user = $request->user();
        $this->ensureAdmin($user);

        $post = CommunityPost::withTrashed()
            ->with(['author', 'disease', 'replies.author'])
            ->find($id);

        if (!$post) {
            return response()->json(['code' => 404, 'message' => 'NOT_FOUND'], 404);
        }

        $author = $post->author;

        $replies = $post->replies()
            ->with('author')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(function ($reply) {
                $author = $reply->author;
                return [
                    'id' => $reply->id,
                    'content' => $reply->content,
                    'status' => $reply->status,
                    'reply_type' => $reply->reply_type,
                    'author' => $author ? [
                        'id' => $author->id,
                        'name' => $author->display_name ?? ($author->name ?: $author->nickname),
                        'role' => $author->role,
                    ] : null,
                    'published_at' => optional($reply->published_at)->toDateTimeString(),
                    'created_at' => optional($reply->created_at)->toDateTimeString(),
                ];
            });

        return response()->json([
            'code' => 0,
            'message' => 'ok',
            'data' => [
                'id' => $post->id,
                'type' => $post->type,
                'title' => $post->title,
                'content' => $post->content,
                'images' => $post->images,
                'status' => $post->status,
                'reject_reason' => $post->reject_reason,
                'category' => $post->category,
                'disease' => $post->disease?->only(['id', 'code', 'name']),
                'likes' => (int) $post->likes,
                'views' => (int) $post->views,
                'replies' => (int) $post->replies_count,
                'author' => $author ? [
                    'id' => $author->id,
                    'name' => $author->display_name ?? ($author->name ?: $author->nickname),
                    'role' => $author->role,
                ] : null,
                'published_at' => optional($post->published_at)->toDateTimeString(),
                'created_at' => optional($post->created_at)->toDateTimeString(),
                'reviewed_at' => optional($post->reviewed_at)->toDateTimeString(),
                'reviewed_by' => $post->reviewer?->only(['id', 'name']),
                'deleted_at' => optional($post->deleted_at)->toDateTimeString(),
                'replies_preview' => $replies,
            ],
        ]);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $this->ensureAdmin($user);

        $post = CommunityPost::withTrashed()->find($id);
        if (!$post) {
            return response()->json(['code' => 404, 'message' => 'NOT_FOUND'], 404);
        }

        $post->approve($user);

        return response()->json(['code' => 0, 'message' => 'ok']);
    }

    public function reject(RejectPostRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        $this->ensureAdmin($user);

        $post = CommunityPost::withTrashed()->find($id);
        if (!$post) {
            return response()->json(['code' => 404, 'message' => 'NOT_FOUND'], 404);
        }

        $payload = $request->validated();

        $post->reject($user, $payload['reason']);

        return response()->json(['code' => 0, 'message' => 'ok']);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $this->ensureAdmin($user);

        $post = CommunityPost::withTrashed()->find($id);
        if (!$post) {
            return response()->json(['code' => 404, 'message' => 'NOT_FOUND'], 404);
        }

        if (!$post->trashed()) {
            $post->delete();
        }

        return response()->json(['code' => 0, 'message' => 'ok']);
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $this->ensureAdmin($user);

        $post = CommunityPost::withTrashed()->find($id);
        if (!$post) {
            return response()->json(['code' => 404, 'message' => 'NOT_FOUND'], 404);
        }

        if ($post->trashed()) {
            $post->restore();
        }

        return response()->json(['code' => 0, 'message' => 'ok']);
    }

    private function ensureAdmin($user): void
    {
        if (!$user || $user->role !== 'super_admin') {
            abort(response()->json(['code' => 403, 'message' => 'FORBIDDEN'], 403));
        }
    }

}
