<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\PaymentProof;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentProofTest extends TestCase
{
    use RefreshDatabase;

    public function test_trade_no_is_optional_when_submitting_payment_proof(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $order = Order::query()->create([
            'user_id' => $user->id,
            'amount' => 88.88,
            'status' => 'pending',
            'channel' => 'manual',
        ]);

        $response = $this->postJson("/api/orders/{$order->id}/payment-proof", [
            'method' => 'manual',
            'amount' => 88.88,
            'images' => ['proof1.jpg'],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'code' => 0,
                'message' => 'ok',
            ]);

        $this->assertDatabaseHas('payment_proofs', [
            'order_id' => $order->id,
            'trade_no' => null,
            'method' => 'manual',
        ]);

        $this->assertEquals('pending', PaymentProof::query()->first()->status);
    }
}
