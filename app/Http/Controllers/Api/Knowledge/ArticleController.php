<?php

namespace App\Http\Controllers\Api\Knowledge;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeArticle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ArticleController extends Controller
{
    // GET /api/knowledge/articles/{id}
    public function show(Request $request, int $id)
    {
        $article = KnowledgeArticle::query()->with('disease')
            ->where('id', $id)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->first();

        if (!$article) {
            return response()->json(['code' => 404, 'message' => 'NOT_FOUND'], 404);
        }

        $related = KnowledgeArticle::query()
            ->where('disease_id', $article->disease_id)
            ->where('id', '<>', $article->id)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderByDesc('views')
            ->limit(2)
            ->get()
            ->map(function (KnowledgeArticle $a) {
                return [
                    'id' => $a->id,
                    'title' => $a->title,
                    'date' => optional($a->published_at)->setTimezone('Asia/Shanghai')?->format('Y-m-d'),
                    'views' => (int) $a->views,
                ];
            })->values();

        return response()->json([
            'code' => 0,
            'message' => 'ok',
            'data' => [
                'id' => $article->id,
                'diseaseCode' => optional($article->disease)->code,
                'title' => $article->title,
                'date' => optional($article->published_at)->setTimezone('Asia/Shanghai')?->format('Y-m-d'),
                'views' => (int) $article->views,
                'bodyHtml' => $article->body_html,
                'related' => $related,
            ],
        ]);
    }

    // POST /api/knowledge/articles/{id}/exposure
    public function exposure(Request $request, int $id)
    {
        $article = KnowledgeArticle::query()
            ->where('id', $id)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->first();

        if (!$article) {
            return response()->json(['code' => 404, 'message' => 'NOT_FOUND'], 404);
        }

        $window = 1800; // 30 分钟
        $user = $request->user();
        $fp = null;
        if ($user) {
            $fp = 'u:' . $user->id;
        } else {
            $deviceId = (string) $request->input('deviceId');
            if ($deviceId !== '') {
                $fp = 'd:' . substr($deviceId, 0, 64);
            } else {
                $ip = $request->ip();
                $ua = (string) $request->header('User-Agent');
                $fp = 'g:' . substr(sha1($ip . '|' . $ua), 0, 32);
            }
        }

        $key = 'kb:art:viewed:' . $article->id . ':' . $fp;
        $counted = false;
        if (Cache::add($key, 1, $window)) {
            // 仅首次进入窗口计数
            KnowledgeArticle::where('id', $article->id)->increment('views');
            $counted = true;
        }

        return response()->json([
            'code' => 0,
            'message' => 'ok',
            'data' => [
                'counted' => $counted,
                'windowSeconds' => $window,
            ],
        ]);
    }
}

