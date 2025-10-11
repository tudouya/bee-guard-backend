<?php

namespace App\Filament\Inspector\Resources;

use App\Support\InspectorNavigation;
use App\Filament\Inspector\Resources\DetectionResource\Pages;

class DetectionResource extends \App\Filament\Admin\Resources\DetectionResource
{
    protected static \UnitEnum|string|null $navigationGroup = InspectorNavigation::GROUP_DETECTION_OPERATIONS;
    protected static ?int $navigationSort = InspectorNavigation::ORDER_DETECTIONS;

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDetections::route('/'),
            'create' => Pages\CreateDetection::route('/create'),
            'edit' => Pages\EditDetection::route('/{record}/edit'),
        ];
    }
}
