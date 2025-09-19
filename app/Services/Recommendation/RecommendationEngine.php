<?php

namespace App\Services\Recommendation;

use App\Models\Detection;
use App\Models\Disease;
use App\Models\Product;
use App\Models\RecommendationRule;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

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
            $out[] = [
                'productId' => $p->id,
                'productName' => (string) $p->name,
                'brief' => (string) ($p->brief ?? ''),
                'url' => $p->url,
                // enterprise = 企业推荐；非 enterprise 展示为平台推荐
                'source' => $sourceLabel,
                'targetType' => filled($p->url) ? 'external' : 'internal',
            ];
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
            ->with(['product:id,name,brief,url,enterprise_id,status']);

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
                ->orderBy('disease_product.priority')
                ->orderBy('products.id');
            foreach ($q->get() as $p) {
                $pushProduct($p, 'platform');
            }
        }

        return $out;
    }
}
