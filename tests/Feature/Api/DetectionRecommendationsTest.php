<?php

namespace Tests\Feature\Api;

use App\Models\Detection;
use App\Models\DetectionCode;
use App\Models\Disease;
use App\Models\Enterprise;
use App\Models\Product;
use App\Models\RecommendationRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DetectionRecommendationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_enterprise_detection_restricts_recommendations_to_enterprise_products(): void
    {
        $user = User::factory()->create();
        $enterprise = Enterprise::factory()->create();
        $otherEnterprise = Enterprise::factory()->create();

        $disease = Disease::query()->create([
            'code' => 'AFB',
            'name' => '美洲幼虫病',
            'status' => 'active',
            'sort' => 1,
        ]);

        $enterpriseProduct = Product::query()->create([
            'enterprise_id' => $enterprise->id,
            'name' => 'Enterprise Treatment',
            'brief' => '企业专属推荐',
            'status' => 'active',
        ]);

        $otherProduct = Product::query()->create([
            'enterprise_id' => $otherEnterprise->id,
            'name' => 'Other Enterprise Product',
            'brief' => '不应被推荐',
            'status' => 'active',
        ]);

        RecommendationRule::query()->create([
            'scope_type' => 'enterprise',
            'applies_to' => 'gift',
            'enterprise_id' => $enterprise->id,
            'disease_id' => $disease->id,
            'product_id' => $enterpriseProduct->id,
            'priority' => 1,
            'tier' => 1,
            'active' => true,
        ]);

        RecommendationRule::query()->create([
            'scope_type' => 'global',
            'applies_to' => 'gift',
            'enterprise_id' => null,
            'disease_id' => $disease->id,
            'product_id' => $otherProduct->id,
            'priority' => 1,
            'tier' => 1,
            'active' => true,
        ]);

        $code = DetectionCode::query()->create([
            'code' => '0001',
            'source_type' => 'gift',
            'prefix' => 'QY',
            'status' => 'used',
            'enterprise_id' => $enterprise->id,
            'assigned_user_id' => $user->id,
        ]);

        $detection = Detection::query()->create([
            'user_id' => $user->id,
            'detection_code_id' => $code->id,
            'sample_no' => 'S-001',
            'sample_types' => ['adult_bee'],
            'status' => 'completed',
            'dna_afb_level' => 'weak',
        ]);

        $response = $this->actingAs($user)->getJson("/api/detections/{$detection->id}");

        $response->assertOk();

        $recommendations = $response->json('recommendations');

        $this->assertIsArray($recommendations);
        $this->assertCount(1, $recommendations);
        $this->assertSame($enterpriseProduct->id, $recommendations[0]['productId']);
        $this->assertSame('Enterprise Treatment', $recommendations[0]['productName']);
    }
}
