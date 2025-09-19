<?php

namespace App\Filament\Admin\Resources\RecommendationRuleResource\Pages;

use App\Filament\Admin\Resources\RecommendationRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\HtmlString;

class ListRecommendationRules extends ListRecords
{
    protected static string $resource = RecommendationRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),

            Actions\Action::make('guide')
                ->label('规则说明')
                ->icon('heroicon-o-question-mark-circle')
                ->modalHeading('推荐规则说明')
                ->modalContent(fn () => view('filament.recommendation-rules-help'))
                ->modalSubmitActionLabel('知道了')
                ->modalWidth('3xl'),
        ];
    }
}
