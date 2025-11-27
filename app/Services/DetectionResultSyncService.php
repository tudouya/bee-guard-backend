<?php

namespace App\Services;

use App\Models\Detection;
use App\Models\Disease;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DetectionResultSyncService
{
    /**
     * 将宽表字段同步到 detection_results（幂等），并清理映射范围内的陈旧记录。
     */
    public function sync(Detection $detection): void
    {
        $map = $this->fieldToDiseaseMap();
        if (empty($map)) {
            return;
        }

        $rows = $this->buildRows($detection, $map);
        $keepIds = array_column($rows, 'disease_id');

        DB::transaction(function () use ($rows, $keepIds, $map, $detection) {
            if (! empty($rows)) {
                DB::table('detection_results')->upsert(
                    $rows,
                    ['detection_id', 'disease_id'],
                    ['level', 'source', 'remark', 'updated_at']
                );
            }

            // 清理映射范围内未覆盖的旧记录，避免留下过期结果
            $mappedIds = array_values($map);
            $idsToDelete = array_diff($mappedIds, $keepIds);
            if (! empty($idsToDelete)) {
                DB::table('detection_results')
                    ->where('detection_id', $detection->id)
                    ->whereIn('disease_id', $idsToDelete)
                    ->delete();
            }
        });
    }

    /**
     * detection_field => disease_id
     * @return array<string,int>
     */
    private function fieldToDiseaseMap(): array
    {
        return Disease::query()
            ->whereNotNull('detection_field')
            ->pluck('id', 'detection_field')
            ->mapWithKeys(fn ($id, $field) => [trim((string) $field) => (int) $id])
            ->all();
    }

    /**
     * 构造 upsert 载荷。RNA/DNA 仅写阳性等级；虫害布尔写 present/absent；null 省略。
     *
     * @param  array<string,int>  $map
     * @return array<int,array<string,mixed>>
     */
    private function buildRows(Detection $detection, array $map): array
    {
        $now = Carbon::now();
        $rows = [];

        $levelFields = [
            'rna_iapv_level',
            'rna_bqcv_level',
            'rna_sbv_level',
            'rna_abpv_level',
            'rna_cbpv_level',
            'rna_dwv_level',
            'dna_afb_level',
            'dna_efb_level',
            'dna_ncer_level',
            'dna_napi_level',
            'dna_cb_level',
        ];

        foreach ($levelFields as $field) {
            if (! isset($map[$field])) {
                continue;
            }
            $val = $detection->{$field} ?? null;
            if (! in_array($val, ['weak', 'medium', 'strong'], true)) {
                continue; // 仅写阳性等级
            }
            $rows[] = [
                'detection_id' => $detection->id,
                'disease_id' => $map[$field],
                'level' => $val,
                'source' => 'manual',
                'remark' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $boolFields = [
            'pest_large_mite',
            'pest_small_mite',
            'pest_wax_moth',
            'pest_small_hive_beetle',
            'pest_shield_mite',
            'pest_scoliidae_wasp',
            'pest_parasitic_bee_fly',
        ];

        foreach ($boolFields as $field) {
            if (! isset($map[$field])) {
                continue;
            }
            $val = $detection->{$field};
            if ($val === null) {
                continue; // 未填写则跳过
            }
            $rows[] = [
                'detection_id' => $detection->id,
                'disease_id' => $map[$field],
                'level' => $val ? 'present' : 'absent',
                'source' => 'manual',
                'remark' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $rows;
    }
}
