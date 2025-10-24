<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

class RegionsSeeder extends Seeder
{
    private const CHUNK_SIZE = 500;

    public function run(): void
    {
        $dataPath = database_path('data/pca-code.json');
        if (!File::exists($dataPath)) {
            throw new RuntimeException('Region data file not found: ' . $dataPath);
        }

        $json = File::get($dataPath);
        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid region data payload.');
        }

        $records = $this->flatten($decoded);

        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            DB::table('regions')->truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        } elseif ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
            DB::table('regions')->truncate();
            DB::statement('PRAGMA foreign_keys = ON');
        } else {
            DB::table('regions')->truncate();
        }

        foreach ($records->chunk(self::CHUNK_SIZE) as $chunk) {
            DB::table('regions')->insert($chunk->all());
        }
    }

    /**
     * @param array<int, array<string, mixed>> $provinces
     */
    private function flatten(array $provinces): Collection
    {
        $rows = collect();

        foreach ($provinces as $province) {
            $provinceCode = (string) ($province['code'] ?? '');
            $provinceName = (string) ($province['name'] ?? '');
            if ($provinceCode === '' || $provinceName === '') {
                continue;
            }

            $rows->push([
                'code' => $provinceCode,
                'name' => $provinceName,
                'province_code' => $provinceCode,
                'city_code' => null,
            ]);

            $cities = $province['children'] ?? [];
            if (!is_array($cities)) {
                continue;
            }

            foreach ($cities as $city) {
                $cityCode = (string) ($city['code'] ?? '');
                $cityName = (string) ($city['name'] ?? '');
                if ($cityCode === '' || $cityName === '') {
                    continue;
                }

                $rows->push([
                    'code' => $cityCode,
                    'name' => $cityName,
                    'province_code' => $provinceCode,
                    'city_code' => $cityCode,
                ]);

                $districts = $city['children'] ?? [];
                if (!is_array($districts)) {
                    continue;
                }

                foreach ($districts as $district) {
                    $districtCode = (string) ($district['code'] ?? '');
                    $districtName = (string) ($district['name'] ?? '');
                    if ($districtCode === '' || $districtName === '') {
                        continue;
                    }

                    $rows->push([
                        'code' => $districtCode,
                        'name' => $districtName,
                        'province_code' => $provinceCode,
                        'city_code' => $cityCode,
                    ]);
                }
            }
        }

        return $rows;
    }
}
