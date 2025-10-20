<?php

namespace Tests\Feature\Api;

use App\Models\Enterprise;
use App\Models\Product;
use App\Models\ProductHomepageImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HomepageRecommendationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        Sanctum::actingAs($user);
    }

    public function test_it_returns_featured_recommendations_with_required_fields(): void
    {
        $enterprise = Enterprise::factory()->create([
            'status' => 'active',
            'contact_phone' => '400-123-8888',
            'contact_wechat' => 'BeeConsult',
            'contact_link' => 'https://enterprise.example.com',
        ]);

        $product = Product::query()->create([
            'enterprise_id' => $enterprise->id,
            'name' => '蜂卫士试剂套装',
            'brief' => '快速筛查蜂病病原，配套操作手册。',
            'url' => 'https://products.example.com/bee-guard',
            'status' => 'active',
            'homepage_featured' => true,
            'homepage_sort_order' => 5,
            'homepage_registration_no' => '国械注准2025-123456',
            'homepage_applicable_scene' => "春繁阶段应急防护\n夏季高温蜂群调理",
            'homepage_highlights' => "3小时快速出结果\n配套技术支持",
            'homepage_cautions' => "开封后尽快使用\n冷链运输储存",
            'homepage_price' => '¥199/套',
            'homepage_contact_company' => '蜂卫士检测中心',
            'homepage_contact_phone' => '400-123-8888',
            'homepage_contact_wechat' => 'BeeConsult',
            'homepage_contact_website' => 'https://consult.example.com',
        ]);

        ProductHomepageImage::query()->create([
            'product_id' => $product->id,
            'path' => 'product-homepage/demo.jpg',
            'position' => 0,
        ]);

        $response = $this->getJson('/api/recommendations/homepage');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.productId', $product->id)
            ->assertJsonPath('data.0.productName', '蜂卫士试剂套装')
            ->assertJsonPath('data.0.sortOrder', 5)
            ->assertJsonPath('data.0.price', '¥199/套')
            ->assertJsonPath('data.0.contact.company', '蜂卫士检测中心')
            ->assertJsonPath('data.0.contact.phone', '400-123-8888')
            ->assertJsonPath('data.0.images.0', fn ($value) => str_contains($value, 'product-homepage/demo.jpg'));

        $payload = $response->json('data.0');

        $this->assertSame(['春繁阶段应急防护', '夏季高温蜂群调理'], $payload['applicableScene']);
        $this->assertSame(['3小时快速出结果', '配套技术支持'], $payload['highlights']);
        $this->assertSame(['开封后尽快使用', '冷链运输储存'], $payload['cautions']);
    }
}
