<?php

namespace App\Filament\Admin\Resources\RecommendationRuleResource\Pages;

use App\Filament\Admin\Resources\RecommendationRuleResource;
use Filament\Resources\Pages\EditRecord;

class EditRecommendationRule extends EditRecord
{
    protected static string $resource = RecommendationRuleResource::class;

    protected function getRedirectUrl(): ?string
    {
        return $this->getResourceUrl();
    }
}

