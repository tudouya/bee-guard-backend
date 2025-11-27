<?php

namespace Tests\Unit;

use App\Models\Detection;
use App\Models\DetectionCode;
use App\Models\DetectionResult;
use App\Models\Disease;
use App\Models\Enterprise;
use App\Models\Product;
use App\Services\Recommendation\RecommendationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RecommendationEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_enterprise_products_are_not_recommended(): void
    {
        $engine = app(RecommendationEngine::class);

        $disease = Disease::create([
            'code' => 'pest_large_mite',
            'name' => '大蜂螨',
            'category' => 'pest',
            'detection_field' => 'pest_large_mite',
            'status' => 'active',
            'sort' => 1,
        ]);

        $activeEnterprise = Enterprise::create([
            'name' => 'Active Ent',
            'status' => 'active',
        ]);
        $inactiveEnterprise = Enterprise::create([
            'name' => 'Inactive Ent',
            'status' => 'inactive',
        ]);

        $productActive = Product::create([
            'enterprise_id' => $activeEnterprise->id,
            'name' => 'Active Pest Product',
            'status' => 'active',
        ]);
        $productInactiveEnt = Product::create([
            'enterprise_id' => $inactiveEnterprise->id,
            'name' => 'Inactive Ent Product',
            'status' => 'active',
        ]);
        $productInactive = Product::create([
            'enterprise_id' => $activeEnterprise->id,
            'name' => 'Inactive Product',
            'status' => 'inactive',
        ]);

        $productActive->diseases()->attach($disease->id);
        $productInactiveEnt->diseases()->attach($disease->id);
        $productInactive->diseases()->attach($disease->id);

        $code = DetectionCode::create([
            'code' => Str::random(6),
            'prefix' => 'ZF',
            'source_type' => 'self_paid',
            'status' => 'used',
        ]);

        $detection = Detection::create([
            'user_id' => null,
            'detection_code_id' => $code->id,
            'sample_no' => 'S-100',
            'status' => 'completed',
        ]);

        DetectionResult::create([
            'detection_id' => $detection->id,
            'disease_id' => $disease->id,
            'level' => 'present',
            'source' => 'manual',
        ]);

        $recommendations = $engine->recommendForDetection($detection, ['pest_large_mite']);

        $productIds = array_column($recommendations, 'productId');
        $this->assertContains($productActive->id, $productIds, 'active enterprise product should be recommended');
        $this->assertNotContains($productInactiveEnt->id, $productIds, 'inactive enterprise product should not be recommended');
        $this->assertNotContains($productInactive->id, $productIds, 'inactive product should not be recommended');
    }
}
