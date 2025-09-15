<?php

namespace Tests\Feature\Api;

use App\Models\DetectionCode;
use App\Models\ShippingNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShippingNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Create a default user and authenticate via Sanctum
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    public function test_submit_shipping_success(): void
    {
        $code = DetectionCode::query()->create([
            'code' => 'NF4TTC6YZU',
            'source_type' => 'self_paid',
            'prefix' => 'ZF',
            'status' => 'assigned',
            'assigned_user_id' => $this->user->id,
            'assigned_at' => now(),
        ]);

        $resp = $this->postJson('/api/shipping-notifications', [
            'detection_number' => 'ZF'.'NF4TTC6YZU',
            'courier_company' => config('shipping.courier_companies')[0],
            'tracking_no' => 'SF1234567890',
            'shipped_at' => '2025-09-15',
        ]);

        $resp->assertStatus(201)
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.detectionNumber', 'ZFNF4TTC6YZU');

        $this->assertDatabaseHas('shipping_notifications', [
            'detection_code_id' => $code->id,
            'tracking_no' => 'SF1234567890',
        ]);
    }

    public function test_submit_shipping_404_when_code_missing(): void
    {
        $resp = $this->postJson('/api/shipping-notifications', [
            'detection_number' => 'ZFNOTEXIST',
            'courier_company' => config('shipping.courier_companies')[0],
            'tracking_no' => 'SF1234567890',
        ]);
        $resp->assertStatus(404);
    }

    public function test_submit_shipping_403_when_not_owned(): void
    {
        $other = User::factory()->create();
        DetectionCode::query()->create([
            'code' => 'NF4TTC6YZU',
            'source_type' => 'self_paid',
            'prefix' => 'ZF',
            'status' => 'assigned',
            'assigned_user_id' => $other->id,
            'assigned_at' => now(),
        ]);

        $resp = $this->postJson('/api/shipping-notifications', [
            'detection_number' => 'ZFNF4TTC6YZU',
            'courier_company' => config('shipping.courier_companies')[0],
            'tracking_no' => 'SF1234567890',
        ]);
        $resp->assertStatus(403);
    }

    public function test_submit_shipping_409_on_duplicate(): void
    {
        $code = DetectionCode::query()->create([
            'code' => 'NF4TTC6YZU',
            'source_type' => 'self_paid',
            'prefix' => 'ZF',
            'status' => 'assigned',
            'assigned_user_id' => $this->user->id,
            'assigned_at' => now(),
        ]);

        ShippingNotification::query()->create([
            'user_id' => $this->user->id,
            'detection_code_id' => $code->id,
            'courier_company' => config('shipping.courier_companies')[0],
            'tracking_no' => 'SF1234567890',
            'shipped_at' => '2025-09-15',
        ]);

        $resp = $this->postJson('/api/shipping-notifications', [
            'detection_number' => 'ZFNF4TTC6YZU',
            'courier_company' => config('shipping.courier_companies')[0],
            'tracking_no' => 'SF1234567890',
            'shipped_at' => '2025-09-15',
        ]);
        $resp->assertStatus(409);
    }

    public function test_validation_errors(): void
    {
        $resp = $this->postJson('/api/shipping-notifications', [
            'detection_number' => 'BAD',
            'courier_company' => '未知物流',
            'tracking_no' => '123',
            'shipped_at' => '2025/09/15',
        ]);
        $resp->assertStatus(422);
    }
}
