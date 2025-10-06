<?php

namespace App\Http\Controllers\Api\Community;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Community\CommunityPostReplyStoreRequest;
use App\Models\CommunityPost;
use App\Models\CommunityPostReply;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReplyController extends Controller
{
    public function index(Request $request, int $postId): JsonResponse
    {
        $post = CommunityPost::query()
            ->where('id', $postId)
            ->where('status', 'approved')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->first();

        if (!$post) {
            return response()->json(['code' => 404, 'message' => 'NOT_FOUND'], 404);
        }

        $perPage = (int) $request->integer('per_page', 10);
        $perPage = $perPage > 50 ? 50 : ($perPage <= 0 ? 10 : $perPage);
        $page = (int) $request->integer('page', 1);

        $query = CommunityPostReply::query()
            ->with(['author'])
            ->where('post_id', $post->id)
            ->whereNull('parent_id')
            ->where('status', 'approved')
            ->orderBy('published_at', 'desc');

        /** @var LengthAwarePaginator $paginator */
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $data = collect($paginator->items())->map(function (CommunityPostReply $reply) {
            return $this->transformReply($reply);
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

    public function store(CommunityPostReplyStoreRequest $request, int $postId): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['code' => 401, 'message' => 'UNAUTHENTICATED'], 401);
        }

        $post = CommunityPost::query()
            ->where('id', $postId)
            ->where('status', 'approved')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->first();

        if (!$post) {
            return response()->json(['code' => 404, 'message' => 'NOT_FOUND'], 404);
        }

        $payload = $request->validated();
        $parentId = $payload['parent_id'] ?? null;
        $parent = null;

        if ($parentId) {
            $parent = CommunityPostReply::query()
                ->where('id', $parentId)
                ->where('post_id', $post->id)
                ->where('status', 'approved')
                ->first();
            if (!$parent) {
                return response()->json(['code' => 422, 'message' => 'PARENT_INVALID'], 422);
            }
        }

        $replyType = match ($user->role) {
            'super_admin' => 'platform',
            'enterprise_admin' => 'enterprise',
            default => 'farmer',
        };

        $reply = CommunityPostReply::query()->create([
            'post_id' => $post->id,
            'user_id' => $user->id,
            'parent_id' => $parent?->id,
            'content' => Str::of($payload['content'])->stripTags()->trim()->toString(),
            'status' => 'pending',
            'reply_type' => $replyType,
        ]);

        return response()->json([
            'code' => 0,
            'message' => 'ok',
            'data' => [
                'id' => $reply->id,
                'status' => $reply->status,
            ],
        ], 201);
    }

    private function transformReply(CommunityPostReply $reply): array
    {
        $author = $reply->author;
        $nickname = $author?->nickname ?: $author?->name ?: '蜂友';
        $role = $author?->role;

        $children = $reply->children()
            ->with('author')
            ->where('status', 'approved')
            ->orderBy('published_at', 'asc')
            ->get()
            ->map(function (CommunityPostReply $child) {
                $author = $child->author;
                $nickname = $author?->nickname ?: $author?->name ?: '蜂友';
                $role = $author?->role;

                return [
                    'id' => $child->id,
                    'content' => $child->content,
                    'reply_type' => $child->reply_type,
                    'author' => [
                        'id' => $author?->id,
                        'nickname' => $nickname,
                        'avatar' => $author?->avatar,
                        'role' => $role,
                    ],
                    'published_at' => optional($child->published_at)->setTimezone('Asia/Shanghai')?->toDateTimeString(),
                ];
            })->values();

        return [
            'id' => $reply->id,
            'content' => $reply->content,
            'reply_type' => $reply->reply_type,
            'author' => [
                'id' => $author?->id,
                'nickname' => $nickname,
                'avatar' => $author?->avatar,
                'role' => $role,
            ],
            'children' => $children,
            'published_at' => optional($reply->published_at)->setTimezone('Asia/Shanghai')?->toDateTimeString(),
        ];
    }
}
