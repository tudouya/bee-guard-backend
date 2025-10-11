<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Detection;
use App\Models\Disease;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use App\Services\Recommendation\RecommendationEngine;

class DetectionsController extends Controller
{
    private const RNA_CODES = ['IAPV','BQCV','SBV','ABPV','CBPV','DWV'];
    private const DNA_CODES = ['AFB','EFB','NCER','NAPI','CB'];

    public function index(Request $request)
    {
        $user = $request->user();

        $query = Detection::query()
            ->with(['detectionCode:id,prefix,code'])
            ->where('user_id', $user->id)
            ->orderByDesc('reported_at')
            ->orderByDesc('tested_at')
            ->orderByDesc('created_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $detections = $query->paginate(
            perPage: (int) $request->query('per_page', 20),
            page: (int) $request->query('page', 1)
        );

        // preload disease names for detail mapping if needed
        $diseaseNames = Disease::query()->pluck('name', 'code')->all();

        $data = $detections->getCollection()->map(function (Detection $d) use ($diseaseNames) {
            $positives = $this->computePositives($d);
            return [
                'id' => $d->id,
                // detectionId: prefix+code for display
                'detectionId' => optional($d->detectionCode)->prefix . optional($d->detectionCode)->code,
                'detectionNumber' => optional($d->detectionCode)->prefix . optional($d->detectionCode)->code,
                'sampleNo' => $d->sample_no,
                'sampleTime' => $this->fmt($d->sampled_at),
                'submitTime' => $this->fmt($d->submitted_at),
                'reportedAt' => $this->fmt($d->reported_at),
                'status' => $d->status,
                'statusText' => $this->statusText($d->status),
                'positiveCount' => count($positives),
                'positives' => array_values($positives),
                // TODO(list-preview): 列表页单条精简推荐预览
                // 使用 RecommendationEngine::firstRecommendationForDetection($d, $positives)
                // 取“首条命中”（规则层按 tier→priority；gift：企业/全局合并；兜底映射先本企业再任意）。
                // 当前暂不返回，保持空对象，后续按需要启用。
                'recommendation' => new \stdClass(),
            ];
        })->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'page' => $detections->currentPage(),
                'per_page' => $detections->perPage(),
                'total' => $detections->total(),
                'has_more' => $detections->hasMorePages(),
            ],
        ]);
    }

    public function show(Request $request, int $id)
    {
        $user = $request->user();

        $builder = Detection::query()
            ->with(['detectionCode:id,prefix,code,source_type,enterprise_id'])
            ->where('user_id', $user->id);

        $detectionNumber = $request->query('detectionNumber');
        if (is_string($detectionNumber) && trim($detectionNumber) !== '') {
            // Allow detail lookup by detection number while preserving user scoping
            $normalized = strtoupper(str_replace('-', '', trim($detectionNumber)));

            $d = $builder
                ->whereHas('detectionCode', function ($query) use ($normalized) {
                    $query->whereRaw('UPPER(CONCAT(prefix, code)) = ?', [$normalized]);
                })
                ->first();

            if (!$d) {
                abort(404);
            }

            if ($id > 0 && $id !== $d->id) {
                abort(404);
            }
        } else {
            $d = $builder->findOrFail($id);
        }

        $diseaseNames = Disease::query()->pluck('name', 'code')->all();

        $results = [];
        foreach ($this->diseaseColumnMap() as $code => $col) {
            $level = $d->{$col};
            $results[] = [
                'code' => $code,
                'name' => $diseaseNames[$code] ?? $code,
                'category' => in_array($code, self::RNA_CODES, true) ? 'rna' : 'dna_bacteria_fungi',
                'level' => $level,
                'levelText' => $this->levelText($level),
                'positive' => in_array($level, ['weak','medium','strong'], true),
            ];
        }

        $positives = $this->computePositives($d);
        // Build recommendations via rules and mappings
        $engine = app(RecommendationEngine::class);
        $recommendations = $engine->recommendForDetection($d, $positives);

        return response()->json([
            'id' => $d->id,
            'detectionId' => optional($d->detectionCode)->prefix . optional($d->detectionCode)->code,
            'detectionNumber' => optional($d->detectionCode)->prefix . optional($d->detectionCode)->code,
            'sampleNo' => $d->sample_no,
            'contactName' => $d->contact_name,
            'address' => $d->address_text,
            'sampleTime' => $this->fmt($d->sampled_at),
            'submitTime' => $this->fmt($d->submitted_at),
            'testedAt' => $this->fmt($d->tested_at),
            'reportedAt' => $this->fmt($d->reported_at),
            'status' => $d->status,
            'statusText' => $this->statusText($d->status),
            'testedBy' => $d->tested_by,
            'reportNo' => $d->report_no,
            'notes' => $d->lab_notes,
            'results' => $results,
            'positiveCount' => count($positives),
            'positives' => array_values($positives),
            'pests' => $this->buildPests($d),
            'recommendations' => $recommendations,
        ]);
    }

    private function diseaseColumnMap(): array
    {
        return [
            // RNA
            'IAPV' => 'rna_iapv_level',
            'BQCV' => 'rna_bqcv_level',
            'SBV'  => 'rna_sbv_level',
            'ABPV' => 'rna_abpv_level',
            'CBPV' => 'rna_cbpv_level',
            'DWV'  => 'rna_dwv_level',
            // DNA/细菌/真菌
            'AFB'  => 'dna_afb_level',
            'EFB'  => 'dna_efb_level',
            'NCER' => 'dna_ncer_level',
            'NAPI' => 'dna_napi_level',
            'CB'   => 'dna_cb_level',
        ];
    }

    private function computePositives(Detection $d): array
    {
        $positives = [];
        foreach ($this->diseaseColumnMap() as $code => $col) {
            $level = $d->{$col};
            if (in_array($level, ['weak','medium','strong'], true)) {
                $positives[] = $code;
            }
        }
        return $positives;
    }

    private function pestColumnMap(): array
    {
        return [
            'large_mite' => ['column' => 'pest_large_mite', 'name' => '大蜂螨'],
            'small_mite' => ['column' => 'pest_small_mite', 'name' => '小蜂螨'],
            'wax_moth' => ['column' => 'pest_wax_moth', 'name' => '巢虫'],
            'small_hive_beetle' => ['column' => 'pest_small_hive_beetle', 'name' => '蜂箱小甲虫'],
            'shield_mite' => ['column' => 'pest_shield_mite', 'name' => '蜂盾螨'],
            'scoliidae_wasp' => ['column' => 'pest_scoliidae_wasp', 'name' => '斯氏蜜蜂茧蜂'],
            'parasitic_bee_fly' => ['column' => 'pest_parasitic_bee_fly', 'name' => '异蚤蜂'],
        ];
    }

    private function buildPests(Detection $d): array
    {
        $items = [];
        foreach ($this->pestColumnMap() as $code => $meta) {
            $value = $d->{$meta['column']};
            $items[] = [
                'code' => $code,
                'name' => $meta['name'],
                'present' => is_null($value) ? null : (bool) $value,
            ];
        }
        return $items;
    }

    private function statusText(string $status): string
    {
        return match ($status) {
            'pending' => '待处理',
            'received' => '已接收',
            'processing' => '检测中',
            'completed' => '已完成',
            default => $status,
        };
    }

    private function levelText(?string $level): string
    {
        return match ($level) {
            'weak' => '弱',
            'medium' => '中',
            'strong' => '强',
            default => '',
        };
    }

    private function fmt($value): ?string
    {
        if (!$value) return null;
        try {
            return $value->format('Y-m-d H:i');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
