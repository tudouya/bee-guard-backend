<?php

namespace App\Filament\Admin\Resources\RecommendationRuleResource\Pages;

use App\Filament\Admin\Resources\RecommendationRuleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRecommendationRule extends CreateRecord
{
    protected static string $resource = RecommendationRuleResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResourceUrl();
    }
}

