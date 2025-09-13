<?php

namespace App\Filament\Admin\Resources\RecommendationRuleResource\Pages;

use App\Filament\Admin\Resources\RecommendationRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRecommendationRules extends ListRecords
{
    protected static string $resource = RecommendationRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

