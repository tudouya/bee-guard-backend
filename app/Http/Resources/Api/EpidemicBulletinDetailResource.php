<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Resources\Json\JsonResource;

class EpidemicBulletinDetailResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->resource['id'] ?? null,
            'title' => $this->resource['title'] ?? null,
            'summary' => $this->resource['summary'] ?? null,
            'content' => $this->resource['content'] ?? null,
            'riskLevel' => $this->resource['risk_level'] ?? null,
            'riskLevelText' => $this->resource['risk_level_text'] ?? '',
            'region' => $this->resource['region'] ?? null,
            'publishedAt' => $this->formatDateTime($this->resource['published_at'] ?? null),
            'source' => $this->resource['source'] ?? null,
            'attachments' => $this->resource['attachments'] ?? [],
        ];
    }

    private function formatDateTime($value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i');
        }

        if (is_string($value)) {
            return substr($value, 0, 16);
        }

        return null;
    }
}
