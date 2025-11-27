<?php

namespace Tests\Feature\Api;

use App\Models\Detection;
use App\Models\DetectionCode;
use App\Models\DetectionResult;
use App\Models\Disease;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DetectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_detail_prefers_detection_results_and_counts_pests(): void
    {
        $user = User::factory()->create();

        $virus = Disease::create([
            'code' => 'IAPV',
            'name' => 'IAPV',
            'category' => 'rna',
            'detection_field' => 'rna_iapv_level',
            'status' => 'active',
            'sort' => 1,
        ]);

        $pest = Disease::create([
            'code' => 'pest_large_mite',
            'name' => '大蜂螨',
            'category' => 'pest',
            'detection_field' => 'pest_large_mite',
            'status' => 'active',
            'sort' => 1,
        ]);

        $code = DetectionCode::create([
            'code' => Str::random(6),
            'source_type' => 'self_paid',
            'prefix' => 'ZF',
            'status' => 'used',
            'assigned_user_id' => $user->id,
            'used_at' => now(),
        ]);

        $detection = Detection::create([
            'user_id' => $user->id,
            'detection_code_id' => $code->id,
            'sample_no' => 'S-001',
            // 宽表为阴性，确保走明细表
            'rna_iapv_level' => null,
            'pest_large_mite' => false,
            'status' => 'completed',
        ]);

        DetectionResult::create([
            'detection_id' => $detection->id,
            'disease_id' => $virus->id,
            'level' => 'weak',
            'source' => 'manual',
        ]);
        DetectionResult::create([
            'detection_id' => $detection->id,
            'disease_id' => $pest->id,
            'level' => 'present',
            'source' => 'manual',
        ]);

        $resp = $this->actingAs($user)->getJson('/api/detections/' . $detection->id);

        $resp->assertOk()
            ->assertJsonPath('positiveCount', 2)
            ->assertJsonPath('positives', ['IAPV', 'pest_large_mite'])
            ->assertJsonFragment(['code' => 'IAPV', 'level' => 'weak', 'positive' => true])
            ->assertJsonFragment(['code' => 'pest_large_mite', 'level' => 'present', 'positive' => true]);
    }

    public function test_detail_falls_back_to_wide_table_when_no_results(): void
    {
        $user = User::factory()->create();

        $d1 = Disease::create([
            'code' => 'SBV',
            'name' => '囊状幼虫病病毒',
            'category' => 'rna',
            'detection_field' => 'rna_sbv_level',
            'status' => 'active',
            'sort' => 1,
        ]);

        $code = DetectionCode::create([
            'code' => Str::random(6),
            'source_type' => 'gift',
            'prefix' => 'QY',
            'status' => 'used',
            'assigned_user_id' => $user->id,
            'used_at' => now(),
        ]);

        $detection = Detection::create([
            'user_id' => $user->id,
            'detection_code_id' => $code->id,
            'sample_no' => 'S-002',
            'status' => 'completed',
        ]);

        DetectionResult::create([
            'detection_id' => $detection->id,
            'disease_id' => $d1->id,
            'level' => 'medium',
            'source' => 'manual',
        ]);

        DetectionResult::create([
            'detection_id' => $detection->id,
            'disease_id' => Disease::create([
                'code' => 'pest_large_mite',
                'name' => '大蜂螨',
                'category' => 'pest',
                'detection_field' => 'pest_large_mite',
                'status' => 'active',
                'sort' => 1,
            ])->id,
            'level' => 'present',
            'source' => 'manual',
        ]);

        $resp = $this->actingAs($user)->getJson('/api/detections/' . $detection->id);

        $resp->assertOk()
            ->assertJsonPath('positiveCount', 2)
            ->assertJsonPath('positives', ['SBV', 'pest_large_mite'])
            ->assertJsonFragment(['code' => 'SBV', 'level' => 'medium', 'positive' => true])
            ->assertJsonFragment(['code' => 'pest_large_mite', 'positive' => true]);
    }
}
