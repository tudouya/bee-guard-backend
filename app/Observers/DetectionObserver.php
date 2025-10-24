<?php

namespace App\Observers;

use App\Models\Detection;
use App\Services\Epidemic\DetectionAggregationService;

class DetectionObserver
{
    public function __construct(
        private readonly DetectionAggregationService $aggregator
    ) {
    }

    public function created(Detection $detection): void
    {
        $this->aggregator->rebuildForDetection($detection);
    }

    public function updated(Detection $detection): void
    {
        $this->aggregator->rebuildForDetection($detection, $detection->getOriginal());
    }

    public function deleted(Detection $detection): void
    {
        $this->aggregator->rebuildForDetection($detection, $detection->getOriginal());
    }
}
