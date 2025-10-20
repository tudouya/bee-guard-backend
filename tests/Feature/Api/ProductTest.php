<?php

namespace Tests\Feature\Api;

use App\Models\Enterprise;
use App\Models\Product;
use App\Models\ProductHomepageImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }

    public function test_show_returns_product_detail_with_homepage_fields(): void
    {
        $enterprise = Enterprise::factory()->create([
            'name' => '蜂卫士生物',
            'intro' => '专注蜂病防控解决方案。',
            'logo_url' => 'enterprise-logos/logo.jpg',
            'contact_phone' => '400-123-4567',
            'contact_wechat' => 'BeeGuardService',
            'contact_link' => 'https://enterprise.example.com',
        ]);

        $product = Product::query()->create([
            'enterprise_id' => $enterprise->id,
            'name' => '蜂卫士试剂套装',
            'brief' => '快速筛查蜂病病原，配套操作手册。',
            'url' => 'https://products.example.com/bee-guard',
            'status' => 'active',
            'media' => ['images' => ['products/bee-guard-1.jpg']],
            'homepage_featured' => true,
            'homepage_sort_order' => 2,
            'homepage_registration_no' => '国械注准2025-123456',
            'homepage_applicable_scene' => "春繁阶段应急防护\n夏季高温蜂群调理",
            'homepage_highlights' => "3小时快速出结果\n配套技术支持",
            'homepage_cautions' => "开封后尽快使用\n冷链运输储存",
            'homepage_price' => '¥199/套',
            'homepage_contact_company' => '蜂卫士检测中心',
            'homepage_contact_phone' => '400-123-4567',
            'homepage_contact_wechat' => 'BeeGuardService',
            'homepage_contact_website' => 'https://consult.example.com',
        ]);

        ProductHomepageImage::query()->create([
            'product_id' => $product->id,
            'path' => 'product-homepage/demo-1.jpg',
            'position' => 0,
        ]);

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertOk()
            ->assertJsonPath('productId', $product->id)
            ->assertJsonPath('productName', '蜂卫士试剂套装')
            ->assertJsonPath('homepage.registrationNo', '国械注准2025-123456')
            ->assertJsonPath('homepage.highlights', ['3小时快速出结果', '配套技术支持'])
            ->assertJsonPath('enterprise.name', '蜂卫士生物')
            ->assertJsonPath('enterprise.contact.phone', '400-123-4567')
            ->assertJsonPath('media.images.0', 'products/bee-guard-1.jpg');

        $payload = $response->json();

        $this->assertIsArray($payload['homepage']['images']);
        $this->assertTrue(str_contains($payload['homepage']['images'][0], 'product-homepage/demo-1.jpg'));
    }

    public function test_show_returns_404_for_inactive_product(): void
    {
        $enterprise = Enterprise::factory()->create();

        $product = Product::query()->create([
            'enterprise_id' => $enterprise->id,
            'name' => '停用产品',
            'status' => 'inactive',
        ]);

        $this->getJson("/api/products/{$product->id}")->assertStatus(404);
    }
}
