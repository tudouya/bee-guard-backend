<?php

namespace Database\Seeders;

use App\Models\Disease;
use Illuminate\Database\Seeder;

class DiseasesBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        // 首批按 Excel 覆盖的病原编码 + 合理中文名（可后续修订）
        $items = [
            ['code' => 'IAPV', 'name' => '以色列急性麻痹病毒'],
            ['code' => 'BQCV', 'name' => '黑后室病毒'],
            ['code' => 'SBV',  'name' => '囊状幼虫病病毒'],
            ['code' => 'ABPV', 'name' => '急性蜜蜂麻痹病毒'],
            ['code' => 'CBPV', 'name' => '慢性蜜蜂麻痹病毒'],
            ['code' => 'DWV',  'name' => '畸形翅病毒'],
            ['code' => 'AFB',  'name' => '美洲幼虫腐臭病'],
            ['code' => 'EFB',  'name' => '欧洲幼虫腐臭病'],
            ['code' => 'NCER', 'name' => '微孢子虫（N. ceranae）'],
            ['code' => 'NAPI', 'name' => '微孢子虫（N. apis）'],
            ['code' => 'CB',   'name' => '白垩病'],
        ];

        foreach ($items as $it) {
            Disease::query()->updateOrCreate(
                ['code' => $it['code']],
                ['name' => $it['name']]
            );
        }
    }
}

