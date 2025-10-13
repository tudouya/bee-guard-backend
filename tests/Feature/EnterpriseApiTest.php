<?php

namespace Tests\Feature;

use App\Models\Enterprise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnterpriseApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_active_enterprises_with_transformed_fields(): void
    {
        $older = Enterprise::factory()->create([
            'services' => '蜂病检测；设备服务',
            'certifications' => '蜂业协会认证,ISO9001',
            'promotions' => '新客户立减100;满三单送一次检测',
            'created_at' => now()->subDay(),
        ]);

        $newer = Enterprise::factory()->create([
            'services' => '疫病防控培训,驻场指导',
            'certifications' => '高新技术企业；省级重点实验室',
            'promotions' => '秋季套餐9折',
            'created_at' => now(),
        ]);

        Enterprise::factory()->create([
            'status' => 'inactive',
        ]);

        $response = $this->getJson('/api/enterprises?per_page=10');

        $response->assertStatus(200)
            ->assertJson([
                'code' => 0,
                'message' => 'ok',
            ]);

        $data = $response->json('data');
        $meta = $response->json('meta');

        $this->assertIsArray($data);
        $this->assertCount(2, $data);
        $this->assertSame($newer->id, $data[0]['id']);
        $this->assertSame($older->id, $data[1]['id']);
        $this->assertSame(['疫病防控培训', '驻场指导'], $data[0]['services']);
        $this->assertSame(['高新技术企业', '省级重点实验室'], $data[0]['certifications']);
        $this->assertSame(['秋季套餐9折'], $data[0]['promotions']);

        $this->assertIsArray($meta);
        $this->assertSame(1, $meta['page']);
        $this->assertSame(10, $meta['per_page']);
        $this->assertSame(2, $meta['total']);
        $this->assertFalse($meta['has_more']);
    }

    public function test_show_returns_detail_with_contact(): void
    {
        $enterprise = Enterprise::factory()->create([
            'services' => '蜂病检测,防控方案定制',
            'certifications' => '蜂业协会认证,ISO9001',
            'promotions' => '合作满一年赠送检测',
            'contact_name' => '李工',
            'contact_phone' => '400-123-4567',
            'contact_wechat' => 'BeeGuardService',
            'contact_link' => 'https://example.com',
        ]);

        $response = $this->getJson('/api/enterprises/' . $enterprise->id);

        $response->assertStatus(200)
            ->assertJson([
                'code' => 0,
                'message' => 'ok',
            ]);

        $data = $response->json('data');
        $this->assertSame($enterprise->id, $data['id']);
        $this->assertSame(['蜂病检测', '防控方案定制'], $data['services']);
        $this->assertSame(['蜂业协会认证', 'ISO9001'], $data['certifications']);
        $this->assertSame(['合作满一年赠送检测'], $data['promotions']);
        $this->assertSame([
            'manager' => '李工',
            'phone' => '400-123-4567',
            'wechat' => 'BeeGuardService',
            'link' => 'https://example.com',
        ], $data['contact']);
    }

    public function test_show_returns_not_found_for_inactive_or_missing(): void
    {
        $enterprise = Enterprise::factory()->create([
            'status' => 'inactive',
        ]);

        $this->getJson('/api/enterprises/' . $enterprise->id)
            ->assertStatus(404)
            ->assertJson([
                'code' => 404,
                'message' => 'NOT_FOUND',
            ]);

        $this->getJson('/api/enterprises/999')->assertStatus(404);
    }

    public function test_contact_is_null_when_details_missing(): void
    {
        $enterprise = Enterprise::factory()->create([
            'contact_name' => null,
            'contact_phone' => null,
            'contact_wechat' => null,
            'contact_link' => null,
        ]);

        $response = $this->getJson('/api/enterprises/' . $enterprise->id);

        $response->assertStatus(200);
        $this->assertNull($response->json('data.contact'));
    }
}
