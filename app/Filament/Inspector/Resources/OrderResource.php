<?php

namespace App\Filament\Inspector\Resources;

use App\Support\InspectorNavigation;
use App\Filament\Inspector\Resources\OrderResource\Pages;

class OrderResource extends \App\Filament\Admin\Resources\OrderResource
{
    protected static \UnitEnum|string|null $navigationGroup = InspectorNavigation::GROUP_PAYMENT;
    protected static ?int $navigationSort = InspectorNavigation::ORDER_ORDERS;

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
        ];
    }
}
