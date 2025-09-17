<?php

namespace App\Services\Community\Rewards;

use App\Enums\RewardMetric;
use App\Jobs\EvaluatePostRewardsJob;
use App\Models\CommunityPost;
use App\Models\RewardRule;
use Illuminate\Support\Collection;

class RewardEvaluator
{
    public function __construct(
        protected RewardIssuer $issuer,
    ) {
    }

    public function evaluatePost(CommunityPost $post, array $metrics): void
    {
        if ($metrics === []) {
            return;
        }

        $metricEnums = collect($metrics)
            ->map(fn ($metric) => $metric instanceof RewardMetric ? $metric : RewardMetric::from($metric))
            ->unique()
            ->all();

        $rules = RewardRule::query()
            ->active()
            ->whereIn('metric', array_map(fn (RewardMetric $metric) => $metric->value, $metricEnums))
            ->with('couponTemplate')
            ->get();

        if ($rules->isEmpty()) {
            return;
        }

        foreach ($rules as $rule) {
            $metric = $rule->metric;
            $currentValue = $this->extractMetricValue($post, $metric);

            if ($currentValue === null) {
                continue;
            }

            if ($currentValue < $rule->threshold) {
                continue;
            }

            $this->issuer->issueForPost($rule, $post);
        }
    }

    protected function extractMetricValue(CommunityPost $post, RewardMetric $metric): ?int
    {
        return match ($metric) {
            RewardMetric::Likes => (int) $post->likes,
            RewardMetric::Views => (int) $post->views,
            RewardMetric::Replies => (int) $post->replies_count,
        };
    }
}
