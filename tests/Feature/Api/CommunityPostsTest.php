<?php

namespace Tests\Feature\Api;

use App\Models\CommunityPost;
use App\Models\CommunityPostLike;
use App\Models\CommunityPostReply;
use App\Models\Disease;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CommunityPostsTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_approved_posts(): void
    {
        $disease = Disease::factory()->create(['status' => 'active']);

        CommunityPost::factory()->approved()->create([
            'type' => 'question',
            'title' => '如何处理春繁蜜蜂',
            'disease_id' => $disease->id,
            'category' => '蜂群动态',
            'published_at' => now()->subDay(),
        ]);

        CommunityPost::factory()->create(['type' => 'question']);
        CommunityPost::factory()->approved()->create(['type' => 'experience']);

        $response = $this->getJson('/api/community/posts?type=question');

        $response->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['title' => '如何处理春繁蜜蜂']);
    }

    public function test_farmer_can_create_post(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => 'farmer']);

        $file = UploadedFile::fake()->image('pic.jpg');
        $uploadResponse = $this->actingAs($user)->postJson('/api/uploads', ['file' => $file]);
        $uploadResponse->assertOk();
        $uploadId = $uploadResponse->json('data.id');

        $payload = [
            'type' => 'experience',
            'title' => '越冬管理经验',
            'content' => str_repeat('经验分享', 20),
            'images' => [$uploadId],
        ];

        $response = $this->actingAs($user)->postJson('/api/community/posts', $payload);

        $response->assertCreated()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('community_posts', [
            'title' => '越冬管理经验',
            'status' => 'pending',
            'user_id' => $user->id,
        ]);
    }

    public function test_show_post_increments_views_and_marks_like(): void
    {
        $user = User::factory()->create(['role' => 'farmer']);
        $post = CommunityPost::factory()->approved()->create([
            'type' => 'experience',
            'views' => 0,
        ]);

        CommunityPostLike::factory()->create([
            'post_id' => $post->id,
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->getJson('/api/community/posts/' . $post->id);

        $response->assertOk()
            ->assertJsonPath('data.id', $post->id)
            ->assertJsonPath('data.liked', true);

        $this->assertDatabaseHas('community_posts', [
            'id' => $post->id,
            'views' => 1,
        ]);
    }

    public function test_farmer_can_reply_to_post(): void
    {
        $post = CommunityPost::factory()->approved()->create();
        $user = User::factory()->create(['role' => 'farmer']);

        $response = $this->actingAs($user)->postJson(
            '/api/community/posts/' . $post->id . '/replies',
            ['content' => '这是我的看法']
        );

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('community_post_replies', [
            'post_id' => $post->id,
            'user_id' => $user->id,
            'status' => 'pending',
        ]);
    }

    public function test_like_and_unlike_post(): void
    {
        $post = CommunityPost::factory()->approved()->create(['likes' => 0]);
        $user = User::factory()->create(['role' => 'farmer']);

        $likeResponse = $this->actingAs($user)->postJson('/api/community/posts/' . $post->id . '/like');
        $likeResponse->assertOk()->assertJsonPath('data.liked', true);

        $this->assertDatabaseHas('community_post_likes', [
            'post_id' => $post->id,
            'user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('community_posts', [
            'id' => $post->id,
            'likes' => 1,
        ]);

        $unlikeResponse = $this->actingAs($user)->deleteJson('/api/community/posts/' . $post->id . '/like');
        $unlikeResponse->assertOk()->assertJsonPath('data.removed', true);

        $this->assertDatabaseMissing('community_post_likes', [
            'post_id' => $post->id,
            'user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('community_posts', [
            'id' => $post->id,
            'likes' => 0,
        ]);
    }
}
