<?php

namespace App\Services\Epidemic;

use App\Models\Detection;
use App\Models\EpidemicMapDataset;
use App\Models\EpidemicMapDatasetEntry;
use App\Models\Region;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class DetectionAggregationService
{
    private const SOURCE_AUTO = 'auto';

    private const POSITIVE_LEVELS = ['weak', 'medium', 'strong'];

    public function rebuildForRegionYear(string $provinceCode, string $districtCode, int $year): void
    {
        if ($provinceCode === '' || $districtCode === '' || $year < 2000) {
            return;
        }

        DB::transaction(function () use ($provinceCode, $districtCode, $year) {
            $dataset = EpidemicMapDataset::query()
                ->where('year', $year)
                ->where('province_code', $provinceCode)
                ->where('district_code', $districtCode)
                ->where('source_type', self::SOURCE_AUTO)
                ->first();

            if ($dataset?->locked) {
                return; // 尊重人工锁定
            }

            $detections = Detection::query()
                ->where('province_code', $provinceCode)
                ->where('district_code', $districtCode)
                ->get();

            $monthStats = [];
            $latest = null;

            foreach ($detections as $detection) {
                $reference = $this->resolveReferenceDate($detection);
                if (!$reference || (int) $reference->year !== $year) {
                    continue;
                }

                $month = (int) $reference->month;
                if (!isset($monthStats[$month])) {
                    $monthStats[$month] = [
                        'total_samples' => 0,
                        'diseases' => [],
                    ];
                }

                $monthStats[$month]['total_samples']++;

                foreach ($this->diseaseColumnMap() as $code => $column) {
                    $level = $detection->{$column} ?? null;
                    if (!in_array($level, self::POSITIVE_LEVELS, true)) {
                        continue;
                    }

                    if (!isset($monthStats[$month]['diseases'][$code])) {
                        $monthStats[$month]['diseases'][$code] = 0;
                    }
                    $monthStats[$month]['diseases'][$code]++;
                }

                if ($latest === null || $reference->greaterThan($latest)) {
                    $latest = $reference;
                }
            }

            if (empty($monthStats)) {
                if ($dataset) {
                    $dataset->entries()->delete();
                    $dataset->delete();
                }
                return;
            }

            if (!$dataset) {
                $dataset = new EpidemicMapDataset([
                    'year' => $year,
                    'province_code' => $provinceCode,
                    'city_code' => $this->defaultCityForDistrict($districtCode),
                    'district_code' => $districtCode,
                    'source_type' => self::SOURCE_AUTO,
                ]);
            } elseif (!$dataset->city_code) {
                $dataset->city_code = $this->defaultCityForDistrict($districtCode);
            }

            $dataset->locked = false;
            $dataset->data_updated_at = $latest;
            $dataset->save();

            $dataset->entries()->delete();

            foreach ($monthStats as $month => $payload) {
                $totalSamples = (int) $payload['total_samples'];
                $diseases = $payload['diseases'];

                if ($totalSamples <= 0 || empty($diseases)) {
                    continue;
                }

                foreach ($diseases as $code => $positiveCases) {
                    EpidemicMapDatasetEntry::query()->create([
                        'dataset_id' => $dataset->id,
                        'month' => $month,
                        'disease_code' => $code,
                        'positive_cases' => $positiveCases,
                        'sample_total' => $totalSamples,
                    ]);
                }
            }

            if ($dataset->entries()->count() === 0) {
                $dataset->delete();
            }
        });
    }

    public function rebuildForDetection(Detection $detection, ?array $original = null): void
    {
        $currentReference = $this->resolveReferenceDate($detection);
        if ($currentReference) {
            $this->rebuildForRegionYear(
                (string) $detection->province_code,
                (string) $detection->district_code,
                (int) $currentReference->year
            );
        }

        if ($original) {
            $originalProvince = (string) Arr::get($original, 'province_code', '');
            $originalDistrict = (string) Arr::get($original, 'district_code', '');
            $originalDate = $this->resolveReferenceDateFromArray($original);

            if ($originalDate && (
                $originalProvince !== (string) $detection->province_code ||
                $originalDistrict !== (string) $detection->district_code ||
                (int) $originalDate->year !== (int) ($currentReference?->year ?? -1)
            )) {
                $this->rebuildForRegionYear($originalProvince, $originalDistrict, (int) $originalDate->year);
            }
        }
    }

    private function resolveReferenceDate(Detection $detection): ?CarbonImmutable
    {
        $timestamp = $detection->reported_at
            ?? $detection->tested_at
            ?? $detection->sampled_at
            ?? $detection->submitted_at
            ?? $detection->created_at;

        if (!$timestamp instanceof CarbonInterface) {
            return null;
        }

        return CarbonImmutable::instance($timestamp);
    }

    private function resolveReferenceDateFromArray(array $attributes): ?CarbonImmutable
    {
        foreach (['reported_at', 'tested_at', 'sampled_at', 'submitted_at', 'created_at'] as $key) {
            $value = Arr::get($attributes, $key);
            if ($value) {
                return CarbonImmutable::parse($value);
            }
        }

        return null;
    }

    private function defaultCityForDistrict(string $districtCode): ?string
    {
        if ($districtCode === '') {
            return null;
        }

        return Region::query()->where('code', $districtCode)->value('city_code');
    }

    private function diseaseColumnMap(): array
    {
        return [
            'IAPV' => 'rna_iapv_level',
            'BQCV' => 'rna_bqcv_level',
            'SBV'  => 'rna_sbv_level',
            'ABPV' => 'rna_abpv_level',
            'CBPV' => 'rna_cbpv_level',
            'DWV'  => 'rna_dwv_level',
            'AFB'  => 'dna_afb_level',
            'EFB'  => 'dna_efb_level',
            'NCER' => 'dna_ncer_level',
            'NAPI' => 'dna_napi_level',
            'CB'   => 'dna_cb_level',
        ];
    }
}
