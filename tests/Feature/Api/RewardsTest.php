<?php

namespace Tests\Feature\Api;

use App\Enums\RewardIssuanceStatus;
use App\Enums\RewardMetric;
use App\Models\CommunityPost;
use App\Models\CouponTemplate;
use App\Models\RewardIssuance;
use App\Models\RewardRule;
use App\Models\User;
use App\Notifications\RewardIssuedNotification;
use App\Services\Community\Rewards\RewardEvaluator;
use App\Services\Community\Rewards\RewardIssuer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RewardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_auto_reward_flow_from_like_to_usage(): void
    {
        Notification::fake();

        $farmer = User::factory()->create(['role' => 'farmer']);
        $template = CouponTemplate::factory()->approved()->create([
            'total_quantity' => 5,
            'platform' => 'jd',
        ]);

        $rule = RewardRule::factory()
            ->automatic()
            ->metric(RewardMetric::Likes, 1)
            ->create([
                'coupon_template_id' => $template->getKey(),
            ]);

        $post = CommunityPost::factory()->approved()->create([
            'user_id' => $farmer->id,
            'likes' => 0,
        ]);

        $this->actingAs($farmer)->postJson('/api/community/posts/' . $post->id . '/like')
            ->assertOk()
            ->assertJsonPath('data.liked', true);

        $issuance = RewardIssuance::query()->first();
        $this->assertNotNull($issuance);
        $this->assertEquals(RewardIssuanceStatus::Issued, $issuance->status);
        $this->assertSame($rule->id, $issuance->reward_rule_id);
        $this->assertSame($post->id, $issuance->community_post_id);

        Notification::assertSentTo(
            $farmer,
            RewardIssuedNotification::class,
            fn (RewardIssuedNotification $notification) => $notification->toDatabase($farmer)['issuance_id'] === $issuance->id
        );

        $listResponse = $this->actingAs($farmer)->getJson('/api/rewards?status=usable');
        $listResponse->assertOk()
            ->assertJsonPath('data.0.rewardId', $issuance->id)
            ->assertJsonPath('data.0.status', 'usable')
            ->assertJsonPath('data.0.platform', '京东');

        $useResponse = $this->actingAs($farmer)->postJson('/api/rewards/' . $issuance->id . '/mark-used');
        $useResponse->assertOk()
            ->assertJsonPath('data.status', 'used');

        $this->assertDatabaseHas('reward_issuances', [
            'id' => $issuance->id,
            'status' => RewardIssuanceStatus::Used->value,
        ]);
    }

    public function test_manual_reward_requires_admin_approval(): void
    {
        Notification::fake();

        $farmer = User::factory()->create(['role' => 'farmer']);
        $admin = User::factory()->create(['role' => 'super_admin']);
        $template = CouponTemplate::factory()->approved()->create([
            'total_quantity' => 10,
        ]);

        $rule = RewardRule::factory()
            ->manual()
            ->metric(RewardMetric::Likes, 1)
            ->create([
                'coupon_template_id' => $template->getKey(),
            ]);

        $post = CommunityPost::factory()->approved()->create([
            'user_id' => $farmer->id,
            'likes' => 1,
        ]);

        app(RewardEvaluator::class)->evaluatePost($post->fresh(), [RewardMetric::Likes]);

        $issuance = RewardIssuance::query()->first();
        $this->assertNotNull($issuance);
        $this->assertEquals(RewardIssuanceStatus::PendingReview, $issuance->status);

        $ackResponse = $this->actingAs($farmer)->postJson('/api/rewards/' . $issuance->id . '/acknowledge');
        $ackResponse->assertOk()
            ->assertJsonPath('data.rewardId', $issuance->id);

        app(RewardIssuer::class)->approveManual($issuance, $admin);

        $issuance = $issuance->fresh();
        $this->assertEquals(RewardIssuanceStatus::Issued, $issuance->status);

        Notification::assertSentTo(
            $farmer,
            RewardIssuedNotification::class,
            fn (RewardIssuedNotification $notification) => $notification->toDatabase($farmer)['issuance_id'] === $issuance->id
        );

        $listResponse = $this->actingAs($farmer)->getJson('/api/rewards?status=usable');
        $listResponse->assertOk()
            ->assertJsonFragment(['rewardId' => $issuance->id])
            ->assertJsonFragment(['status' => 'usable']);
    }

    public function test_reward_summary_returns_counts(): void
    {
        $farmer = User::factory()->create(['role' => 'farmer']);
        $template = CouponTemplate::factory()->approved()->create();
        $rule = RewardRule::factory()->automatic()->metric(RewardMetric::Likes, 1)->create([
            'coupon_template_id' => $template->getKey(),
        ]);

        $post = CommunityPost::factory()->approved()->create([
            'user_id' => $farmer->id,
            'likes' => 1,
        ]);

        app(\App\Services\Community\Rewards\RewardEvaluator::class)->evaluatePost($post, [RewardMetric::Likes]);

        $summary = $this->actingAs($farmer)->getJson('/api/rewards/summary');
        $summary->assertOk()
            ->assertJsonPath('data.pending', 0)
            ->assertJsonPath('data.usable', 1)
            ->assertJsonPath('data.used', 0)
            ->assertJsonPath('data.expired', 0);
    }
}
