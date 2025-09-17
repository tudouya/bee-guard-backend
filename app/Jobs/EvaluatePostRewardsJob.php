<?php

namespace App\Jobs;

use App\Models\CommunityPost;
use App\Services\Community\Rewards\RewardEvaluator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EvaluatePostRewardsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param array<int, string> $metrics
     */
    public function __construct(
        protected int $postId,
        protected array $metrics
    ) {
    }

    public function handle(RewardEvaluator $evaluator): void
    {
        $post = CommunityPost::query()->find($this->postId);

        if (! $post) {
            return;
        }

        $evaluator->evaluatePost($post, $this->metrics);
    }
}
