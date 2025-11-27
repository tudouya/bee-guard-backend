<?php

namespace App\Console\Commands;

use App\Models\Detection;
use App\Models\Disease;
use App\Models\DetectionResult;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BackfillDetectionResults extends Command
{
    protected $signature = 'detection-results:backfill {--from-id= : 起始 detection id} {--to-id= : 结束 detection id} {--chunk=500 : 批大小} {--dry-run : 仅预览，不写入}';

    protected $description = '将宽表检测结果回填到 detection_results 表（幂等），支持 id 范围与 dry-run';

    private array $diseaseMapByField = [];

    public function handle(): int
    {
        $this->buildDiseaseFieldMap();

        $fromId = $this->option('from-id') ? (int) $this->option('from-id') : null;
        $toId = $this->option('to-id') ? (int) $this->option('to-id') : null;
        $chunk = max(100, (int) $this->option('chunk'));
        $dry = (bool) $this->option('dry-run');

        $query = Detection::query()->orderBy('id');
        if ($fromId) {
            $query->where('id', '>=', $fromId);
        }
        if ($toId) {
            $query->where('id', '<=', $toId);
        }

        $total = $query->count();
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $written = 0;
        $skipped = 0;
        $missingMapping = 0;

        $query->chunk($chunk, function (Collection $detections) use (&$written, &$skipped, &$missingMapping, $bar, $dry) {
            $payload = [];
            foreach ($detections as $detection) {
                $rows = $this->buildRowsForDetection($detection);
                if (empty($rows)) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                foreach ($rows as $row) {
                    if (! isset($this->diseaseMapByField[$row['field']])) {
                        $missingMapping++;
                        $this->warn("缺少 disease 映射: field={$row['field']} detection_id={$detection->id}");
                        continue;
                    }

                    $payload[] = [
                        'detection_id' => $detection->id,
                        'disease_id' => $this->diseaseMapByField[$row['field']],
                        'level' => $row['level'],
                        'source' => 'backfill',
                        'remark' => null,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ];
                }

                $bar->advance();
            }

            if ($dry || empty($payload)) {
                return;
            }

            DB::table('detection_results')->upsert(
                $payload,
                ['detection_id', 'disease_id'],
                ['level', 'source', 'remark', 'updated_at']
            );
            $written += count($payload);
        });

        $bar->finish();
        $this->newLine();

        $this->info("总计：{$total}，写入/更新：{$written}，跳过：{$skipped}，缺少映射：{$missingMapping}");

        return self::SUCCESS;
    }

    private function buildDiseaseFieldMap(): void
    {
        // 需要在 diseases 表中配置 detection_field，对应宽表字段名
        $this->diseaseMapByField = Disease::query()
            ->whereNotNull('detection_field')
            ->pluck('id', 'detection_field')
            ->mapWithKeys(fn ($id, $field) => [trim((string) $field) => (int) $id])
            ->all();

        if (empty($this->diseaseMapByField)) {
            $this->warn('diseases 表未配置 detection_field，回填将无法写入任何记录');
        }
    }

    private function buildRowsForDetection(Detection $detection): array
    {
        $rows = [];

        // RNA/DNA 等级字段
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
            $val = $detection->{$field} ?? null;
            if (in_array($val, ['weak', 'medium', 'strong'], true)) {
                $rows[] = ['field' => $field, 'level' => $val];
            } elseif ($val === 'negative' || $val === null || $val === '') {
                // skip negative/null to avoid noise
                continue;
            }
        }

        // 虫害等布尔字段
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
            $val = $detection->{$field};
            if ($val === null) {
                continue;
            }
            $rows[] = [
                'field' => $field,
                'level' => $val ? 'present' : 'absent',
            ];
        }

        return $rows;
    }
}
