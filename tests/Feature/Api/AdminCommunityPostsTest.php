<?php

namespace Tests\Feature\Api;

use App\Models\CommunityPost;
use App\Models\CommunityPostReply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCommunityPostsTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_list_posts(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        CommunityPost::factory()->count(2)->create(['type' => 'question']);
        CommunityPost::factory()->approved()->create(['type' => 'experience']);

        $response = $this->actingAs($admin)
            ->getJson('/api/admin/community/posts');

        $response->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonCount(3, 'data');
    }

    public function test_non_admin_cannot_access_admin_routes(): void
    {
        $user = User::factory()->create(['role' => 'farmer']);

        $response = $this->actingAs($user)
            ->getJson('/api/admin/community/posts');

        $response->assertStatus(403);
    }

    public function test_super_admin_can_approve_post(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $post = CommunityPost::factory()->create(['status' => 'pending']);

        $this->actingAs($admin)
            ->postJson('/api/admin/community/posts/' . $post->id . '/approve')
            ->assertOk();

        $this->assertDatabaseHas('community_posts', [
            'id' => $post->id,
            'status' => 'approved',
            'reviewed_by' => $admin->id,
        ]);
    }

    public function test_super_admin_can_reject_post_with_reason(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $post = CommunityPost::factory()->create(['status' => 'pending']);

        $this->actingAs($admin)
            ->postJson('/api/admin/community/posts/' . $post->id . '/reject', [
                'reason' => '内容不符合要求',
            ])
            ->assertOk();

        $this->assertDatabaseHas('community_posts', [
            'id' => $post->id,
            'status' => 'rejected',
            'reject_reason' => '内容不符合要求',
        ]);
    }

    public function test_super_admin_can_delete_and_restore_post(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $post = CommunityPost::factory()->approved()->create();

        $this->actingAs($admin)
            ->deleteJson('/api/admin/community/posts/' . $post->id)
            ->assertOk();

        $this->assertSoftDeleted('community_posts', ['id' => $post->id]);

        $this->actingAs($admin)
            ->postJson('/api/admin/community/posts/' . $post->id . '/restore')
            ->assertOk();

        $this->assertDatabaseHas('community_posts', ['id' => $post->id, 'deleted_at' => null]);
    }

    public function test_super_admin_can_approve_reply_and_refresh_count(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $post = CommunityPost::factory()->approved()->create();
        $reply = CommunityPostReply::factory()->create([
            'post_id' => $post->id,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->postJson('/api/admin/community/replies/' . $reply->id . '/approve')
            ->assertOk();

        $this->assertDatabaseHas('community_post_replies', [
            'id' => $reply->id,
            'status' => 'approved',
            'reviewed_by' => $admin->id,
        ]);

        $this->assertDatabaseHas('community_posts', [
            'id' => $post->id,
            'replies_count' => 1,
        ]);
    }

    public function test_super_admin_can_reject_reply(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $post = CommunityPost::factory()->approved()->create();
        $reply = CommunityPostReply::factory()->create([
            'post_id' => $post->id,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->postJson('/api/admin/community/replies/' . $reply->id . '/reject', [
                'reason' => '不符合规范',
            ])
            ->assertOk();

        $this->assertDatabaseHas('community_post_replies', [
            'id' => $reply->id,
            'status' => 'rejected',
            'reject_reason' => '不符合规范',
        ]);
    }

    public function test_super_admin_can_delete_and_restore_reply(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $post = CommunityPost::factory()->approved()->create(['replies_count' => 1]);
        $reply = CommunityPostReply::factory()->approved()->create([
            'post_id' => $post->id,
        ]);

        $this->actingAs($admin)
            ->deleteJson('/api/admin/community/replies/' . $reply->id)
            ->assertOk();

        $this->assertSoftDeleted('community_post_replies', ['id' => $reply->id]);
        $this->assertDatabaseHas('community_posts', [
            'id' => $post->id,
            'replies_count' => 0,
        ]);

        $this->actingAs($admin)
            ->postJson('/api/admin/community/replies/' . $reply->id . '/restore')
            ->assertOk();

        $this->assertDatabaseHas('community_post_replies', ['id' => $reply->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('community_posts', [
            'id' => $post->id,
            'replies_count' => 1,
        ]);
    }
}
