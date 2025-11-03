<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enterprise;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class EnterpriseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 10);
        $perPage = $perPage > 50 ? 50 : ($perPage <= 0 ? 10 : $perPage);
        $page = (int) $request->integer('page', 1);

        $query = Enterprise::query()
            ->where('status', 'active')
            ->orderByDesc('created_at');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $data = collect($paginator->items())->map(function (Enterprise $enterprise) {
            return [
                'id' => $enterprise->id,
                'name' => $enterprise->name,
                'logo' => $this->resolveLogo($enterprise->logo_url),
                'summary' => $enterprise->intro,
                'services' => $this->explodeList($enterprise->services),
                'certifications' => $this->explodeList($enterprise->certifications),
                'promotions' => $this->formatPromotion($enterprise->promotions),
                'contact' => $this->formatContact($enterprise),
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
                'has_more' => $paginator->hasMorePages(),
            ],
        ]);
    }

    public function show(int $enterpriseId): JsonResponse
    {
        $enterprise = Enterprise::query()
            ->where('id', $enterpriseId)
            ->where('status', 'active')
            ->first();

        if (!$enterprise) {
            return response()->json([
                'code' => 404,
                'message' => 'NOT_FOUND',
            ], 404);
        }

        return response()->json([
            'code' => 0,
            'message' => 'ok',
            'data' => [
                'id' => $enterprise->id,
                'name' => $enterprise->name,
                'logo' => $this->resolveLogo($enterprise->logo_url),
                'summary' => $enterprise->intro,
                'services' => $this->explodeList($enterprise->services),
                'certifications' => $this->explodeList($enterprise->certifications),
                'promotions' => $this->formatPromotion($enterprise->promotions),
                'contact' => $this->formatContact($enterprise),
            ],
        ]);
    }

    private function explodeList(?string $value): array
    {
        if (empty($value)) {
            return [];
        }

        $segments = preg_split('/\r\n|\n|\r/u', $value) ?: [];

        return collect($segments)
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->values()
            ->all();
    }

    private function formatPromotion(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : preg_replace("/\r\n?/", "\n", $trimmed);
    }

    private function formatContact(Enterprise $enterprise): ?array
    {
        $contact = [
            'manager' => $enterprise->contact_name,
            'phone' => $enterprise->contact_phone,
            'wechat' => $enterprise->contact_wechat,
            'link' => $enterprise->contact_link,
        ];

        $hasValue = collect($contact)->some(fn ($value) => filled($value));

        return $hasValue ? $contact : null;
    }

    private function resolveLogo(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        if (Str::startsWith($value, ['http://', 'https://'])) {
            return $value;
        }

        $s3Url = rtrim((string) config('filesystems.disks.s3.url'), '/');

        if ($s3Url !== '') {
            return $s3Url . '/' . ltrim($value, '/');
        }

        $candidateDisks = array_values(array_unique(array_filter([
            config('filament.default_filesystem_disk'),
            config('filesystems.default'),
            'public',
            's3',
        ])));

        foreach ($candidateDisks as $disk) {
            $configuredUrl = config("filesystems.disks.{$disk}.url");

            if (is_string($configuredUrl) && $configuredUrl !== '') {
                return rtrim($configuredUrl, '/') . '/' . ltrim($value, '/');
            }

            try {
                $url = Storage::disk($disk)->url($value);
            } catch (InvalidArgumentException $e) {
                continue;
            }

            if (blank($url)) {
                continue;
            }

            return $url;
        }

        return null;
    }
}
