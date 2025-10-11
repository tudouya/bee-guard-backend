<?php

namespace App\Filament\Inspector\Resources;

use App\Support\InspectorNavigation;
use App\Filament\Inspector\Resources\ShippingNotificationResource\Pages;

class ShippingNotificationResource extends \App\Filament\Admin\Resources\ShippingNotificationResource
{
    protected static \UnitEnum|string|null $navigationGroup = InspectorNavigation::GROUP_DETECTION_OPERATIONS;
    protected static ?int $navigationSort = InspectorNavigation::ORDER_SHIPPING;

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShippingNotifications::route('/'),
            'view' => Pages\ViewShippingNotification::route('/{record}'),
        ];
    }
}
