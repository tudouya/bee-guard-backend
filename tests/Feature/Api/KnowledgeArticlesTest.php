<?php

namespace Tests\Feature\Api;

use App\Models\Disease;
use App\Models\KnowledgeArticle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KnowledgeArticlesTest extends TestCase
{
    use RefreshDatabase;

    public function test_articles_are_sorted_by_sort_value(): void
    {
        $disease = Disease::factory()->create([
            'code' => 'T.mite',
            'sort' => 1,
        ]);

        KnowledgeArticle::query()->create([
            'disease_id' => $disease->id,
            'title' => 'High Sort',
            'brief' => 'High sort brief',
            'body_html' => '<p>Content</p>',
            'published_at' => now()->subDays(1),
            'views' => 10,
            'sort' => 20,
        ]);

        KnowledgeArticle::query()->create([
            'disease_id' => $disease->id,
            'title' => 'Lowest Sort',
            'brief' => 'Lowest sort brief',
            'body_html' => '<p>Content</p>',
            'published_at' => now()->subDays(2),
            'views' => 5,
            'sort' => 5,
        ]);

        KnowledgeArticle::query()->create([
            'disease_id' => $disease->id,
            'title' => 'Medium Sort',
            'brief' => 'Medium sort brief',
            'body_html' => '<p>Content</p>',
            'published_at' => now()->subDays(3),
            'views' => 7,
            'sort' => 10,
        ]);

        KnowledgeArticle::query()->create([
            'disease_id' => $disease->id,
            'title' => 'Draft Article',
            'brief' => 'Should be excluded',
            'body_html' => '<p>Draft Content</p>',
            'published_at' => null,
            'views' => 0,
            'sort' => 1,
        ]);

        $otherDisease = Disease::factory()->create([
            'code' => 'OTHER',
            'sort' => 2,
        ]);

        KnowledgeArticle::query()->create([
            'disease_id' => $otherDisease->id,
            'title' => 'Other Disease Article',
            'brief' => 'Other disease brief',
            'body_html' => '<p>Other Content</p>',
            'published_at' => now()->subDay(),
            'views' => 3,
            'sort' => 1,
        ]);

        $response = $this->getJson('/api/knowledge/diseases/T.mite/articles?per_page=10');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');

        $this->assertSame(
            ['Lowest Sort', 'Medium Sort', 'High Sort'],
            array_column($response->json('data'), 'title')
        );
    }

    public function test_featured_articles_endpoint_returns_latest_four(): void
    {
        $disease = Disease::factory()->create([
            'code' => 'FEATURE',
            'sort' => 1,
        ]);

        $baseTime = now();

        // Create six featured, published articles with descending timestamps
        foreach (range(0, 5) as $offset) {
            KnowledgeArticle::query()->create([
                'disease_id' => $disease->id,
                'title' => 'Featured Article ' . $offset,
                'brief' => 'Brief ' . $offset,
                'body_html' => '<p>Content ' . $offset . '</p>',
                'published_at' => $baseTime->copy()->subMinutes($offset),
                'views' => $offset,
                'sort' => $offset,
                'is_homepage_featured' => true,
            ]);
        }

        // Non-featured article should be ignored
        KnowledgeArticle::query()->create([
            'disease_id' => $disease->id,
            'title' => 'Non Featured',
            'brief' => 'Should not appear',
            'body_html' => '<p>Non featured</p>',
            'published_at' => $baseTime->copy()->addMinute(),
            'views' => 99,
            'sort' => 1,
            'is_homepage_featured' => false,
        ]);

        // Featured but unpublished should be ignored
        KnowledgeArticle::query()->create([
            'disease_id' => $disease->id,
            'title' => 'Draft Featured',
            'brief' => 'Should not appear',
            'body_html' => '<p>Draft</p>',
            'published_at' => null,
            'views' => 0,
            'sort' => 1,
            'is_homepage_featured' => true,
        ]);

        $response = $this->getJson('/api/knowledge/articles/featured');

        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(4, $data);
        $this->assertSame(
            [
                'Featured Article 0',
                'Featured Article 1',
                'Featured Article 2',
                'Featured Article 3',
            ],
            array_column($data, 'title')
        );

        foreach ($data as $item) {
            $this->assertSame('FEATURE', $item['diseaseCode']);
        }
    }
}
