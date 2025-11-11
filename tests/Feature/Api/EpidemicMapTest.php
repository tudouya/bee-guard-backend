<?php

namespace Tests\Feature\Api;

use App\Models\Detection;
use App\Models\Disease;
use App\Models\EpidemicMapDataset;
use App\Models\EpidemicMapDatasetEntry;
use App\Models\Region;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EpidemicMapTest extends TestCase
{
    use RefreshDatabase;

    public function test_pie_endpoint_returns_grouped_data(): void
    {
        $provinceCode = '11';
        $cityCode = '1101';
        $districtCode = '110101';

        Region::query()->create([
            'code' => $provinceCode,
            'name' => '北京市',
            'province_code' => $provinceCode,
            'city_code' => null,
        ]);
        Region::query()->create([
            'code' => $cityCode,
            'name' => '市辖区',
            'province_code' => $provinceCode,
            'city_code' => $cityCode,
        ]);
        Region::query()->create([
            'code' => $districtCode,
            'name' => '东城区',
            'province_code' => $provinceCode,
            'city_code' => $cityCode,
        ]);

        Disease::query()->create([
            'code' => 'SBV',
            'name' => '囊状幼虫病',
            'map_alias' => '囊状幼虫病',
            'map_color' => '#F05A5A',
            'status' => 'active',
            'sort' => 0,
            'map_order' => 0,
        ]);
        Disease::query()->create([
            'code' => 'NOSEMA',
            'name' => '微孢子虫',
            'map_alias' => '微孢子虫',
            'map_color' => '#65C18C',
            'status' => 'active',
            'sort' => 1,
            'map_order' => 1,
        ]);

        $current = EpidemicMapDataset::query()->create([
            'year' => 2025,
            'province_code' => $provinceCode,
            'city_code' => $cityCode,
            'district_code' => $districtCode,
            'source_type' => 'manual',
            'data_updated_at' => now()->subDay(),
        ]);
        $current->entries()->create([
            'month' => 1,
            'disease_code' => 'SBV',
            'positive_cases' => 12,
            'sample_total' => 48,
        ]);
        $current->entries()->create([
            'month' => 1,
            'disease_code' => 'NOSEMA',
            'positive_cases' => 6,
            'sample_total' => 48,
        ]);

        $previous = EpidemicMapDataset::query()->create([
            'year' => 2024,
            'province_code' => $provinceCode,
            'city_code' => $cityCode,
            'district_code' => $districtCode,
            'source_type' => 'manual',
            'data_updated_at' => now()->subDays(10),
        ]);
        $previous->entries()->create([
            'month' => 1,
            'disease_code' => 'SBV',
            'positive_cases' => 8,
            'sample_total' => 36,
        ]);

        $response = $this->getJson(sprintf(
            '/api/epidemic/map/pie?province_code=%s&district_code=%s&year=2025&compare_year=2024',
            $provinceCode,
            $districtCode
        ));

        $response->assertOk()
            ->assertJsonPath('data.region.provinceName', '北京市')
            ->assertJsonPath('data.region.districtName', '东城区')
            ->assertJsonPath('data.groups.0.key', 'current')
            ->assertJsonPath('data.groups.0.months.0.hasData', true)
            ->assertJsonPath('data.groups.1.key', 'previous')
            ->assertJsonPath('data.groups.1.months.0.hasData', true)
            ->assertJsonPath('data.availableMonths.0', 1);

        $legend = $response->json('data.legend');
        $this->assertCount(2, $legend);
        $legendByCode = collect($legend)->keyBy('code');
        $this->assertSame('SBV', $legendByCode['SBV']['label']);
        $this->assertSame('NOSEMA', $legendByCode['NOSEMA']['label']);

        $currentSlices = collect($response->json('data.groups.0.months.0.slices'))
            ->keyBy('diseaseCode');
        $this->assertSame(12, $currentSlices['SBV']['positive']);
        $this->assertSame(48, $currentSlices['SBV']['samples']);
        $this->assertSame('#F05A5A', $currentSlices['SBV']['color']);
        $this->assertSame('SBV', $currentSlices['SBV']['label']);
        $this->assertArrayHasKey('rate', $currentSlices['SBV']);

        $monthTwo = collect($response->json('data.groups.0.months'))->firstWhere('monthValue', 2);
        $this->assertFalse($monthTwo['hasData']);
    }

    public function test_pie_endpoint_supports_month_filtering(): void
    {
        $provinceCode = '11';
        $cityCode = '1101';
        $districtCode = '110101';

        Region::query()->create([
            'code' => $provinceCode,
            'name' => '北京市',
            'province_code' => $provinceCode,
            'city_code' => null,
        ]);
        Region::query()->create([
            'code' => $cityCode,
            'name' => '市辖区',
            'province_code' => $provinceCode,
            'city_code' => $cityCode,
        ]);
        Region::query()->create([
            'code' => $districtCode,
            'name' => '东城区',
            'province_code' => $provinceCode,
            'city_code' => $cityCode,
        ]);

        Disease::query()->create([
            'code' => 'SBV',
            'name' => '囊状幼虫病',
            'map_alias' => '囊状幼虫病',
            'map_color' => '#F05A5A',
            'status' => 'active',
            'sort' => 0,
            'map_order' => 0,
        ]);

        $dataset = EpidemicMapDataset::query()->create([
            'year' => 2025,
            'province_code' => $provinceCode,
            'city_code' => $cityCode,
            'district_code' => $districtCode,
            'source_type' => 'manual',
            'data_updated_at' => now()->subDay(),
        ]);
        $dataset->entries()->createMany([
            [
                'month' => 1,
                'disease_code' => 'SBV',
                'positive_cases' => 10,
                'sample_total' => 40,
            ],
            [
                'month' => 2,
                'disease_code' => 'SBV',
                'positive_cases' => 8,
                'sample_total' => 32,
            ],
        ]);

        $response = $this->getJson(sprintf(
            '/api/epidemic/map/pie?province_code=%s&district_code=%s&year=2025&month=2',
            $provinceCode,
            $districtCode
        ));

        $response->assertOk()
            ->assertJsonPath('data.groups.0.months.0.monthValue', 2)
            ->assertJsonMissingPath('data.groups.0.months.1')
            ->assertJsonPath('data.availableMonths.0', 1)
            ->assertJsonPath('data.availableMonths.1', 2);

        $slices = $response->json('data.groups.0.months.0.slices');
        $this->assertCount(1, $slices);
        $this->assertSame(8, $slices[0]['positive']);
    }

    public function test_pie_endpoint_merges_manual_and_auto_sources(): void
    {
        $provinceCode = '14';
        $cityCode = '1408';
        $districtCode = '140825';

        Region::query()->create([
            'code' => $provinceCode,
            'name' => '山西省',
            'province_code' => $provinceCode,
            'city_code' => null,
        ]);
        Region::query()->create([
            'code' => $cityCode,
            'name' => '运城市',
            'province_code' => $provinceCode,
            'city_code' => $cityCode,
        ]);
        Region::query()->create([
            'code' => $districtCode,
            'name' => '新绛县',
            'province_code' => $provinceCode,
            'city_code' => $cityCode,
        ]);

        Disease::query()->create([
            'code' => 'AFB',
            'name' => '美洲幼虫病',
            'map_alias' => '美洲幼虫病',
            'map_color' => '#F05A5A',
            'status' => 'active',
            'sort' => 0,
            'map_order' => 0,
        ]);

        $manual = EpidemicMapDataset::query()->create([
            'year' => 2025,
            'province_code' => $provinceCode,
            'city_code' => $cityCode,
            'district_code' => $districtCode,
            'source_type' => 'manual',
            'data_updated_at' => now()->subDays(2),
        ]);
        $manual->entries()->create([
            'month' => 3,
            'disease_code' => 'AFB',
            'positive_cases' => 7,
            'sample_total' => 40,
        ]);

        $auto = EpidemicMapDataset::query()->create([
            'year' => 2025,
            'province_code' => $provinceCode,
            'city_code' => $cityCode,
            'district_code' => $districtCode,
            'source_type' => 'auto',
            'data_updated_at' => now()->subDay(),
        ]);
        $auto->entries()->create([
            'month' => 3,
            'disease_code' => 'AFB',
            'positive_cases' => 4,
            'sample_total' => 20,
        ]);

        $response = $this->getJson(sprintf(
            '/api/epidemic/map/pie?province_code=%s&district_code=%s&year=2025&month=3',
            $provinceCode,
            $districtCode
        ));

        $response->assertOk();

        $slices = $response->json('data.groups.0.months.0.slices');
        $this->assertSame(11, $slices[0]['positive']);
        $this->assertSame(60, $slices[0]['samples']);
        $this->assertEqualsWithDelta(11 / 60, $slices[0]['rate'], 0.000001);
        $this->assertEqualsCanonicalizing(['manual', 'auto'], $slices[0]['sources']);
    }

    public function test_detection_creation_aggregates_into_auto_dataset(): void
    {
        $provinceCode = '11';
        $cityCode = '1101';
        $districtCode = '110101';

        Region::query()->create([
            'code' => $provinceCode,
            'name' => '北京市',
            'province_code' => $provinceCode,
            'city_code' => null,
        ]);
        Region::query()->create([
            'code' => $cityCode,
            'name' => '市辖区',
            'province_code' => $provinceCode,
            'city_code' => $cityCode,
        ]);
        Region::query()->create([
            'code' => $districtCode,
            'name' => '东城区',
            'province_code' => $provinceCode,
            'city_code' => $cityCode,
        ]);

        Carbon::setTestNow(Carbon::create(2025, 1, 15, 9, 0));

        Detection::query()->create([
            'sample_no' => 'S-001',
            'status' => 'completed',
            'province_code' => $provinceCode,
            'city_code' => $cityCode,
            'district_code' => $districtCode,
            'reported_at' => Carbon::now(),
            'dna_afb_level' => 'medium',
        ]);

        Carbon::setTestNow();

        $dataset = EpidemicMapDataset::query()
            ->where('source_type', 'auto')
            ->where('province_code', $provinceCode)
            ->where('district_code', $districtCode)
            ->where('year', 2025)
            ->first();

        $this->assertNotNull($dataset, 'Auto dataset should be generated');

        $entry = EpidemicMapDatasetEntry::query()
            ->where('dataset_id', $dataset->id)
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame(1, $entry->positive_cases);
        $this->assertSame(1, $entry->sample_total);
        $this->assertEqualsWithDelta(1.0, (float) $entry->rate, 0.00001);
    }

    public function test_detection_update_rebuilds_auto_dataset(): void
    {
        $provinceCode = '11';
        $cityCode = '1101';
        $districtCode = '110101';

        Region::query()->create([
            'code' => $provinceCode,
            'name' => '北京市',
            'province_code' => $provinceCode,
            'city_code' => null,
        ]);
        Region::query()->create([
            'code' => $cityCode,
            'name' => '市辖区',
            'province_code' => $provinceCode,
            'city_code' => $cityCode,
        ]);
        Region::query()->create([
            'code' => $districtCode,
            'name' => '东城区',
            'province_code' => $provinceCode,
            'city_code' => $cityCode,
        ]);

        Carbon::setTestNow(Carbon::create(2025, 2, 10, 9, 0));

        $detection = Detection::query()->create([
            'sample_no' => 'S-002',
            'status' => 'completed',
            'province_code' => $provinceCode,
            'city_code' => $cityCode,
            'district_code' => $districtCode,
            'reported_at' => Carbon::now(),
            'dna_afb_level' => 'weak',
        ]);

        $dataset = EpidemicMapDataset::query()
            ->where('source_type', 'auto')
            ->where('province_code', $provinceCode)
            ->where('district_code', $districtCode)
            ->where('year', 2025)
            ->first();

        $this->assertNotNull($dataset);
        $entry = EpidemicMapDatasetEntry::query()->where('dataset_id', $dataset->id)->first();
        $this->assertNotNull($entry);

        // 更新为阴性，触发聚合重算
        $detection->update(['dna_afb_level' => null]);

        $dataset = EpidemicMapDataset::query()
            ->where('source_type', 'auto')
            ->where('province_code', $provinceCode)
            ->where('district_code', $districtCode)
            ->where('year', 2025)
            ->first();

        $this->assertNull($dataset, 'Dataset should be removed when无阳性数据');

        Carbon::setTestNow();
    }
}
