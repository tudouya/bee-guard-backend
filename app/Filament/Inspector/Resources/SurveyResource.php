<?php

namespace App\Filament\Inspector\Resources;

use App\Support\InspectorNavigation;
use App\Filament\Inspector\Resources\SurveyResource\Pages;

class SurveyResource extends \App\Filament\Admin\Resources\SurveyResource
{
    protected static \UnitEnum|string|null $navigationGroup = InspectorNavigation::GROUP_DETECTION_OPERATIONS;
    protected static ?int $navigationSort = InspectorNavigation::ORDER_SURVEYS;

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSurveys::route('/'),
            'view' => Pages\ViewSurvey::route('/{record}'),
        ];
    }
}
