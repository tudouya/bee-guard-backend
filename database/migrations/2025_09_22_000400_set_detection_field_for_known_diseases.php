<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('diseases')) {
            return;
        }

        $now = now();

        // detection_field => [code, name, category]
        $mappings = [
            // RNA
            'rna_iapv_level' => ['IAPV', '以色列急性麻痹病毒', 'rna'],
            'rna_bqcv_level' => ['BQCV', '黑后室病毒', 'rna'],
            'rna_sbv_level' => ['SBV', '囊状幼虫病病毒', 'rna'],
            'rna_abpv_level' => ['ABPV', '急性蜜蜂麻痹病毒', 'rna'],
            'rna_cbpv_level' => ['CBPV', '慢性蜜蜂麻痹病毒', 'rna'],
            'rna_dwv_level' => ['DWV', '畸形翅病毒', 'rna'],
            // DNA/细菌/真菌
            'dna_afb_level' => ['AFB', '美洲幼虫腐臭病', 'dna'],
            'dna_efb_level' => ['EFB', '欧洲幼虫腐臭病', 'dna'],
            'dna_ncer_level' => ['NCER', '微孢子虫（N. ceranae）', 'dna'],
            'dna_napi_level' => ['NAPI', '微孢子虫（N. apis）', 'dna'],
            'dna_cb_level' => ['CB', '白垩病', 'dna'],
            // 虫害（布尔）
            'pest_large_mite' => ['pest_large_mite', '大蜂螨', 'pest'],
            'pest_small_mite' => ['pest_small_mite', '小蜂螨', 'pest'],
            'pest_wax_moth' => ['pest_wax_moth', '巢虫', 'pest'],
            'pest_small_hive_beetle' => ['pest_small_hive_beetle', '蜂箱小甲虫', 'pest'],
            'pest_shield_mite' => ['pest_shield_mite', '蜂盾螨', 'pest'],
            'pest_scoliidae_wasp' => ['pest_scoliidae_wasp', '斯氏蜜蜂茧蜂', 'pest'],
            'pest_parasitic_bee_fly' => ['pest_parasitic_bee_fly', '异蚤蜂', 'pest'],
        ];

        foreach ($mappings as $field => [$code, $name, $category]) {
            $existing = DB::table('diseases')->where('code', $code)->first();

            if ($existing) {
                $updates = [];
                if (empty($existing->detection_field)) {
                    $updates['detection_field'] = $field;
                }
                if (empty($existing->category)) {
                    $updates['category'] = $category;
                }
                if (! empty($updates)) {
                    $updates['updated_at'] = $now;
                    DB::table('diseases')->where('id', $existing->id)->update($updates);
                }
                continue;
            }

            DB::table('diseases')->insert([
                'code' => $code,
                'name' => $name,
                'category' => $category,
                'detection_field' => $field,
                'status' => 'active',
                'sort' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // 不移除数据，避免删除已有配置
    }
};
