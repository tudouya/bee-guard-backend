<?php

namespace Tests\Feature\Api;

use App\Models\EpidemicBulletin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EpidemicBulletinsTest extends TestCase
{
    use RefreshDatabase;

    public function test_featured_bulletins_endpoint_returns_latest_four_with_thumbnail(): void
    {
        $baseTime = now();

        foreach (range(0, 5) as $offset) {
            EpidemicBulletin::query()->create([
                'title' => 'Featured Bulletin ' . $offset,
                'summary' => 'Summary ' . $offset,
                'content' => '<p>Body ' . $offset . '</p>',
                'risk_level' => EpidemicBulletin::RISK_LOW,
                'status' => EpidemicBulletin::STATUS_PUBLISHED,
                'homepage_featured' => true,
                'thumbnail_url' => 'https://cdn.example.com/bulletin-' . $offset . '.jpg',
                'published_at' => $baseTime->copy()->subMinutes($offset),
            ]);
        }

        EpidemicBulletin::query()->create([
            'title' => 'Non Featured Bulletin',
            'summary' => 'Should not be returned',
            'content' => '<p>Body</p>',
            'risk_level' => EpidemicBulletin::RISK_LOW,
            'status' => EpidemicBulletin::STATUS_PUBLISHED,
            'homepage_featured' => false,
            'thumbnail_url' => 'https://cdn.example.com/not-featured.jpg',
            'published_at' => $baseTime->copy()->subMinute(),
        ]);

        EpidemicBulletin::query()->create([
            'title' => 'Future Featured Bulletin',
            'summary' => 'Should be excluded because of future publish time',
            'content' => '<p>Body</p>',
            'risk_level' => EpidemicBulletin::RISK_LOW,
            'status' => EpidemicBulletin::STATUS_PUBLISHED,
            'homepage_featured' => true,
            'thumbnail_url' => 'https://cdn.example.com/future.jpg',
            'published_at' => $baseTime->copy()->addDay(),
        ]);

        EpidemicBulletin::query()->create([
            'title' => 'Draft Featured Bulletin',
            'summary' => 'Should be excluded because status is draft',
            'content' => '<p>Body</p>',
            'risk_level' => EpidemicBulletin::RISK_LOW,
            'status' => EpidemicBulletin::STATUS_DRAFT,
            'homepage_featured' => true,
            'thumbnail_url' => 'https://cdn.example.com/draft.jpg',
            'published_at' => $baseTime->copy()->subMinute(),
        ]);

        $response = $this->getJson('/api/epidemic/bulletins/featured');

        $response->assertOk();
        $response->assertJsonPath('code', 0);
        $response->assertJsonPath('message', 'ok');

        $data = $response->json('data');
        $this->assertCount(4, $data);

        $this->assertSame(
            [
                'Featured Bulletin 0',
                'Featured Bulletin 1',
                'Featured Bulletin 2',
                'Featured Bulletin 3',
            ],
            array_column($data, 'title')
        );

        foreach ($data as $item) {
            $this->assertNotEmpty($item['thumbnailUrl']);
            $this->assertSame('low', $item['riskLevel']);
            $this->assertArrayHasKey('region', $item);
        }
    }
}
