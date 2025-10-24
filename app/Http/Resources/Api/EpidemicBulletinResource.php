<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Resources\Json\JsonResource;

class EpidemicBulletinResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->resource['id'] ?? null,
            'title' => $this->resource['title'] ?? null,
            'summary' => $this->resource['summary'] ?? null,
            'riskLevel' => $this->resource['risk_level'] ?? null,
            'riskLevelText' => $this->resource['risk_level_text'] ?? '',
            'region' => $this->resource['region'] ?? null,
            'publishedAt' => $this->formatDate($this->resource['published_at'] ?? null),
        ];
    }

    private function formatDate($value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_string($value)) {
            return substr($value, 0, 10);
        }

        return null;
    }
}
