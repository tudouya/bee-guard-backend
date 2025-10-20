<?php

namespace App\Services\Recommendation;

use App\Models\Detection;
use App\Models\Disease;
use App\Models\Product;
use App\Models\RecommendationRule;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class RecommendationEngine
{
    /**
     * TODO(list-preview): 列表页首条精简推荐
     * 需求：在 /api/detections 列表每条记录返回一条“精简推荐”以便预览。
     * 规则：与详情一致（gift 合并企业/全局，按 tier→priority；再 disease_product 兜底，先本企业再任意），
     *       但仅取首条命中（每层 limit 1，命中即返回），并返回与详情相同的字段形状。
     * 性能：分页默认20条时，每条最多触发若干次小查询（limit 1）；可缓存 disease code→id 映射。
     */
    public function firstRecommendationForDetection(Detection $d, array $positiveDiseaseCodes): ?array
    {
        // TODO: 实现首条推荐查询与拼装；当前占位以维持兼容
        return null;
    }

    /**
     * Build recommendations for a detection record.
     *
     * Priority:
     * - If code is gift and bound to an enterprise E:
     *   1) enterprise rules (scope=enterprise, enterprise_id=E, applies_to in ('gift','any'))
     *   2) global rules (scope=global, applies_to in ('gift','any'))
     *   3) disease_product mapping: prefer products of enterprise E first, then any
     * - If code is self_paid:
     *   1) global rules (applies_to in ('self_paid','any'))
     *   2) disease_product mapping (any enterprise)
     *
     * Filters:
     * - Only active rules and within time window
     * - Only products with status=active
     * - De-duplicate by product_id, maintain order
     *
     * @param  Detection  $d
     * @param  array<int,string>  $positiveDiseaseCodes
     * @return array<int,array<string,mixed>>
     */
    public function recommendForDetection(Detection $d, array $positiveDiseaseCodes): array
    {
        $code = $d->detectionCode; // may be null in edge cases
        if (!$code) {
            return [];
        }

        $source = (string) ($code->source_type ?? 'self_paid');
        $enterpriseId = $code->enterprise_id ? (int) $code->enterprise_id : null;

        if (empty($positiveDiseaseCodes)) {
            return [];
        }

        $diseaseIds = Disease::query()
            ->whereIn('code', array_values($positiveDiseaseCodes))
            ->pluck('id')
            ->all();

        if (empty($diseaseIds)) {
            return [];
        }

        $now = now();
        $selectedProductIds = [];
        $out = [];

        // Helper to append product to output if not seen
        $pushProduct = function (Product $p, string $sourceLabel) use (&$out, &$selectedProductIds) {
            if (in_array((int) $p->id, $selectedProductIds, true)) {
                return;
            }
            $selectedProductIds[] = (int) $p->id;
            $out[] = $this->formatProduct($p, $sourceLabel);
        };

        // Query active rules within window helper
        $activeRulesQuery = fn () => RecommendationRule::query()
            ->where('active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->whereIn('disease_id', $diseaseIds)
            ->whereHas('product', function ($q) {
                $q->where('status', 'active');
            })
            ->with(['product' => fn ($query) => $query
                ->with([
                    'enterprise:id,name,contact_phone,contact_wechat,contact_link',
                    'homepageImages',
                ])
            ]);

        // Applies_to filter based on source
        $applies = $source === 'gift' ? ['gift', 'any'] : ['self_paid', 'any'];

        if ($source === 'gift' && $enterpriseId) {
            // Enterprise + Global rules merged, ordered by tier then priority
            $rules = (clone $activeRulesQuery)()
                ->whereIn('applies_to', $applies)
                ->where(function ($q) use ($enterpriseId) {
                    $q->where('scope_type', 'global')
                      ->orWhere(function ($q2) use ($enterpriseId) {
                          $q2->where('scope_type', 'enterprise')->where('enterprise_id', $enterpriseId);
                      });
                })
                ->orderBy('tier')
                ->orderBy('priority')
                ->get();
            foreach ($rules as $r) {
                if ($r->product) {
                    $pushProduct($r->product, $r->scope_type === 'enterprise' ? 'enterprise' : 'platform');
                }
            }

            // 3) Fallback mapping: prefer enterprise products first
            // 3.1 enterprise products mapped to diseases
            $q1 = Product::query()
                ->select('products.*')
                ->join('disease_product', 'products.id', '=', 'disease_product.product_id')
                ->whereIn('disease_product.disease_id', $diseaseIds)
                ->where('products.status', 'active')
                ->where('products.enterprise_id', $enterpriseId)
                ->with([
                    'enterprise:id,name,contact_phone,contact_wechat,contact_link',
                    'homepageImages',
                ])
                ->orderBy('disease_product.priority')
                ->orderBy('products.id');
            foreach ($q1->get() as $p) {
                $pushProduct($p, 'platform');
            }

            // 3.2 any enterprise products mapped to diseases, excluding already selected
            $q2 = Product::query()
                ->select('products.*')
                ->join('disease_product', 'products.id', '=', 'disease_product.product_id')
                ->whereIn('disease_product.disease_id', $diseaseIds)
                ->where('products.status', 'active')
                ->when(!empty($selectedProductIds), fn ($q) => $q->whereNotIn('products.id', $selectedProductIds))
                ->with([
                    'enterprise:id,name,contact_phone,contact_wechat,contact_link',
                    'homepageImages',
                ])
                ->orderBy('disease_product.priority')
                ->orderBy('products.id');
            foreach ($q2->get() as $p) {
                $pushProduct($p, 'platform');
            }
        } else {
            // self_paid or missing enterprise -> global rules only, then generic mapping
            // 1) Global rules ordered by tier then priority
            $rules = (clone $activeRulesQuery)()
                ->where('scope_type', 'global')
                ->whereIn('applies_to', $applies)
                ->orderBy('tier')
                ->orderBy('priority')
                ->get();
            foreach ($rules as $r) {
                if ($r->product) {
                    $pushProduct($r->product, 'platform');
                }
            }

            // 2) disease_product mapping (any enterprise)
            $q = Product::query()
                ->select('products.*')
                ->join('disease_product', 'products.id', '=', 'disease_product.product_id')
                ->whereIn('disease_product.disease_id', $diseaseIds)
                ->where('products.status', 'active')
                ->when(!empty($selectedProductIds), fn ($qb) => $qb->whereNotIn('products.id', $selectedProductIds))
                ->with([
                    'enterprise:id,name,contact_phone,contact_wechat,contact_link',
                    'homepageImages',
                ])
                ->orderBy('disease_product.priority')
                ->orderBy('products.id');
            foreach ($q->get() as $p) {
                $pushProduct($p, 'platform');
            }
        }

        return $out;
    }

    public function homepageRecommendations(): array
    {
        $products = Product::query()
            ->where('homepage_featured', true)
            ->where('status', 'active')
            ->with([
                'homepageImages',
                'enterprise:id,name,contact_phone,contact_wechat,contact_link',
            ])
            ->orderBy('homepage_sort_order')
            ->orderBy('id')
            ->get();

        return $products->map(function (Product $product) {
            $source = $product->enterprise_id ? 'enterprise' : 'platform';
            $formatted = $this->formatProduct($product, $source);

            return array_merge(
                Arr::only($formatted, ['productId', 'productName', 'brief', 'url', 'source', 'targetType']),
                [
                    'sortOrder' => (int) ($product->homepage_sort_order ?? 0),
                    'images' => $formatted['homepage']['images'] ?? [],
                    'registrationNo' => $formatted['homepage']['registrationNo'] ?? null,
                    'applicableScene' => $formatted['homepage']['applicableScene'] ?? [],
                    'highlights' => $formatted['homepage']['highlights'] ?? [],
                    'cautions' => $formatted['homepage']['cautions'] ?? [],
                    'price' => $formatted['homepage']['price'] ?? null,
                    'contact' => $formatted['homepage']['contact'] ?? null,
                ]
            );
        })->all();
    }

    public function productDetail(int $productId): ?array
    {
        $product = Product::query()
            ->with([
                'homepageImages',
                'enterprise',
            ])
            ->find($productId);

        if (!$product || $product->status !== 'active') {
            return null;
        }

        $source = $product->enterprise_id ? 'enterprise' : 'platform';
        $base = $this->formatProduct($product, $source);

        $media = $product->media ?? null;

        $enterprise = $product->enterprise
            ? [
                'id' => $product->enterprise->id,
                'name' => $product->enterprise->name,
                'intro' => $this->trimNullable($product->enterprise->intro ?? null),
                'logoUrl' => $this->resolveImageUrl($product->enterprise->logo_url ?? null),
                'contact' => [
                    'phone' => $this->trimNullable($product->enterprise->contact_phone ?? null),
                    'wechat' => $this->trimNullable($product->enterprise->contact_wechat ?? null),
                    'website' => $this->trimNullable($product->enterprise->contact_link ?? null),
                ],
            ]
            : null;

        return array_merge($base, [
            'media' => $media,
            'enterprise' => $enterprise,
        ]);
    }

    private function formatProduct(Product $product, string $sourceLabel): array
    {
        $product->loadMissing([
            'homepageImages',
            'enterprise:id,name,contact_phone,contact_wechat,contact_link',
        ]);

        $data = [
            'productId' => $product->id,
            'productName' => (string) $product->name,
            'brief' => (string) ($product->brief ?? ''),
            'url' => $product->url,
            'source' => $sourceLabel,
            'targetType' => filled($product->url) ? 'external' : 'internal',
        ];

        $data['homepage'] = $product->homepage_featured ? [
            'images' => $product->homepageImages
                ->sortBy('position')
                ->map(fn ($image) => $this->resolveImageUrl($image->path))
                ->filter()
                ->values()
                ->all(),
            'registrationNo' => $this->trimNullable($product->homepage_registration_no),
            'applicableScene' => $this->splitLines($product->homepage_applicable_scene),
            'highlights' => $this->splitLines($product->homepage_highlights),
            'cautions' => $this->splitLines($product->homepage_cautions),
            'price' => $this->trimNullable($product->homepage_price),
            'contact' => $this->buildContact($product),
        ] : null;

        return $data;
    }

    private function buildContact(Product $product): ?array
    {
        $contact = [
            'company' => $this->trimNullable($product->homepage_contact_company) ?? optional($product->enterprise)->name,
            'phone' => $this->trimNullable($product->homepage_contact_phone),
            'wechat' => $this->trimNullable($product->homepage_contact_wechat),
            'website' => $this->trimNullable($product->homepage_contact_website) ?? $this->trimNullable(optional($product->enterprise)->contact_link),
        ];

        $hasValue = collect($contact)->some(fn ($value) => filled($value));

        return $hasValue ? $contact : null;
    }

    private function splitLines(?string $value): array
    {
        if ($value === null) {
            return [];
        }

        $normalized = preg_replace("/\r\n?/", "\n", trim($value));

        if ($normalized === '') {
            return [];
        }

        return collect(explode("\n", $normalized))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values()
            ->all();
    }

    private function trimNullable(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function resolveImageUrl(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $value)) {
            return $value;
        }

        $disk = config('filament.default_filesystem_disk') ?: config('filesystems.default', 'public');

        try {
            return Storage::disk($disk)->url($value);
        } catch (\Throwable $e) {
            try {
                return Storage::disk('public')->url($value);
            } catch (\Throwable $e2) {
                return null;
            }
        }
    }
}
