<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Profile\ProfileUpdateRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'code' => 0,
            'message' => 'ok',
            'data' => [
                'avatar' => $this->normalizeAvatarUrl((string) ($user->avatar ?? '')),
                'nickname' => (string) ($user->nickname ?? ''),
                'phone' => (string) ($user->phone ?? ''),
            ],
        ]);
    }

    public function update(ProfileUpdateRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        if (array_key_exists('nickname', $data)) {
            $nickname = is_string($data['nickname']) ? $data['nickname'] : '';
            $user->nickname = ($nickname === '') ? null : $nickname;
        }
        if (array_key_exists('avatar', $data)) {
            $normalized = $this->normalizeAvatarInput($request->input('avatar'));
            $user->avatar = $normalized; // null to clear
        }
        $user->save();

        return response()->json([
            'code' => 0,
            'message' => 'ok',
            'data' => [
                'avatar' => $this->normalizeAvatarUrl((string) ($user->avatar ?? '')),
                'nickname' => (string) ($user->nickname ?? ''),
                'phone' => (string) ($user->phone ?? ''),
            ],
        ]);
    }

    private function normalizeAvatarUrl(string $value): string
    {
        if ($value === '') {
            return '';
        }
        if (Str::startsWith($value, ['http://', 'https://'])) {
            return $value;
        }

        $s3Url = rtrim((string) config('filesystems.disks.s3.url'), '/');

        if ($s3Url !== '') {
            return $s3Url . '/' . ltrim($value, '/');
        }

        $disk = config('filesystems.default', 'public');

        if (! config()->has("filesystems.disks.{$disk}")) {
            $disk = 'public';
        }

        // If looks like a path stored on the configured disk, try to convert to URL
        try {
            $path = Storage::disk($disk)->url($value);
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                return $path;
            }
            // Convert relative path to absolute URL using current app URL
            return \Illuminate\Support\Facades\URL::to($path);
        } catch (\Throwable $e) {
            // Fallback: if it's a relative path, convert to absolute; else return as-is
            if ($value !== '' && $value[0] === '/') {
                return \Illuminate\Support\Facades\URL::to($value);
            }
            return (string) $value;
        }
    }

    private function normalizeAvatarInput(mixed $value): ?string
    {
        if ($value === null) {
            return null; // clear
        }
        $raw = trim((string) $value);
        if ($raw === '') {
            return null; // clear
        }

        // Accept forms:
        // 1) avatars/... (relative path)
        // 2) /storage/avatars/... (public URL path)
        // 3) APP_URL/storage/avatars/... (absolute within our domain)

        $extOk = static function (string $p): bool {
            return (bool) preg_match('/\.(jpg|jpeg|png|webp)(\?.*)?$/i', $p);
        };

        // 1) avatars/...
        if (str_starts_with($raw, 'avatars/')) {
            if ($extOk($raw)) return $raw;
            $this->invalidAvatar();
        }

        // 2) /storage/avatars/...
        if (str_starts_with($raw, '/storage/avatars/')) {
            $path = ltrim(substr($raw, strlen('/storage/')), '/'); // avatars/...
            if ($extOk($path)) return $path;
            $this->invalidAvatar();
        }

        // 3) APP_URL/storage/avatars/...
        if (str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://')) {
            $appUrl = (string) config('app.url');
            $app = parse_url($appUrl) ?: [];
            $in = parse_url($raw) ?: [];
            $sameHost = ($app['host'] ?? null) && ($in['host'] ?? null) && strtolower($app['host']) === strtolower($in['host']);
            $samePort = ($app['port'] ?? null) === ($in['port'] ?? null);
            $sameScheme = ($app['scheme'] ?? null) === ($in['scheme'] ?? null);
            if ($sameHost && $samePort && $sameScheme) {
                $path = (string) ($in['path'] ?? '');
                if (str_starts_with($path, '/storage/avatars/')) {
                    $rel = ltrim(substr($path, strlen('/storage/')), '/');
                    if ($extOk($rel)) return $rel;
                    $this->invalidAvatar();
                }
            }
            // external or non-storage url not supported
            $this->invalidAvatar();
        }

        // Unsupported form
        $this->invalidAvatar();
    }

    private function invalidAvatar(): never
    {
        throw \Illuminate\Validation\ValidationException::withMessages([
            'avatar' => ['头像地址不合法（仅支持本站存储路径）'],
        ]);
    }
}
