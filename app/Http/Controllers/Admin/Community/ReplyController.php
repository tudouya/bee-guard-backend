<?php

namespace App\Http\Controllers\Admin\Community;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Community\RejectReplyRequest;
use App\Models\CommunityPostReply;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReplyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensureAdmin($user);

        $perPage = (int) $request->integer('per_page', 15);
        $perPage = $perPage > 100 ? 100 : ($perPage <= 0 ? 15 : $perPage);
        $page = (int) $request->integer('page', 1);

        $query = CommunityPostReply::query()->with(['author', 'post']);

        $status = (string) $request->query('status');
        if ($status !== '') {
            $query->where('status', $status);
        }

        $postId = (int) $request->integer('post_id');
        if ($postId > 0) {
            $query->where('post_id', $postId);
        }

        $author = trim((string) $request->query('author'));
        if ($author !== '') {
            $query->whereHas('author', function ($q) use ($author) {
                $q->where('name', 'like', "%{$author}%")
                    ->orWhere('nickname', 'like', "%{$author}%")
                    ->orWhere('username', 'like', "%{$author}%");
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

        $data = collect($paginator->items())->map(function (CommunityPostReply $reply) {
            $author = $reply->author;
            $post = $reply->post;
            return [
                'id' => $reply->id,
                'post' => $post ? [
                    'id' => $post->id,
                    'title' => $post->title,
                    'status' => $post->status,
                ] : null,
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
                'deleted_at' => optional($reply->deleted_at)->toDateTimeString(),
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

    public function approve(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $this->ensureAdmin($user);

        $reply = CommunityPostReply::withTrashed()->with('post')->find($id);
        if (!$reply) {
            return response()->json(['code' => 404, 'message' => 'NOT_FOUND'], 404);
        }

        $reply->approve($user);

        return response()->json(['code' => 0, 'message' => 'ok']);
    }

    public function reject(RejectReplyRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        $this->ensureAdmin($user);

        $reply = CommunityPostReply::withTrashed()->with('post')->find($id);
        if (!$reply) {
            return response()->json(['code' => 404, 'message' => 'NOT_FOUND'], 404);
        }

        $payload = $request->validated();
        $reply->reject($user, $payload['reason']);

        return response()->json(['code' => 0, 'message' => 'ok']);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $this->ensureAdmin($user);

        $reply = CommunityPostReply::withTrashed()->with('post')->find($id);
        if (!$reply) {
            return response()->json(['code' => 404, 'message' => 'NOT_FOUND'], 404);
        }

        if (!$reply->trashed()) {
            $reply->delete();
        }

        $reply->post?->refreshReplyCount();

        return response()->json(['code' => 0, 'message' => 'ok']);
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $this->ensureAdmin($user);

        $reply = CommunityPostReply::withTrashed()->with('post')->find($id);
        if (!$reply) {
            return response()->json(['code' => 404, 'message' => 'NOT_FOUND'], 404);
        }

        if ($reply->trashed()) {
            $reply->restore();
        }

        if ($reply->status === 'approved') {
            $reply->post?->refreshReplyCount();
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
