<?php

namespace App\Http\Controllers\Api\Community;

use App\Http\Controllers\Controller;
use App\Models\CommunityPost;
use App\Models\CommunityPostLike;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LikeController extends Controller
{
    public function store(Request $request, int $postId): JsonResponse
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

        $liked = false;
        DB::transaction(function () use ($post, $user, &$liked) {
            $exists = CommunityPostLike::query()
                ->where('post_id', $post->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($exists) {
                $liked = true;
                return;
            }

            CommunityPostLike::query()->create([
                'post_id' => $post->id,
                'user_id' => $user->id,
            ]);

            CommunityPost::query()->where('id', $post->id)->increment('likes');
            $liked = true;
        });

        $likes = CommunityPost::query()->where('id', $post->id)->value('likes') ?? $post->likes;

        return response()->json([
            'code' => 0,
            'message' => 'ok',
            'data' => [
                'liked' => $liked,
                'likes' => (int) $likes,
            ],
        ]);
    }

    public function destroy(Request $request, int $postId): JsonResponse
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

        $removed = false;
        DB::transaction(function () use ($post, $user, &$removed) {
            $like = CommunityPostLike::query()
                ->where('post_id', $post->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (!$like) {
                return;
            }

            $like->delete();
            CommunityPost::query()->where('id', $post->id)->where('likes', '>', 0)->decrement('likes');
            $removed = true;
        });

        $likes = CommunityPost::query()->where('id', $post->id)->value('likes') ?? $post->likes;

        return response()->json([
            'code' => 0,
            'message' => 'ok',
            'data' => [
                'liked' => false,
                'likes' => (int) $likes,
                'removed' => $removed,
            ],
        ]);
    }
}
