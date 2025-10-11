<?php

namespace App\Filament\Inspector\Resources\OrderResource\Pages;

use App\Filament\Inspector\Resources\OrderResource;
use App\Filament\Admin\Resources\OrderResource\Pages\ListOrders as BaseListOrders;

class ListOrders extends BaseListOrders
{
    protected static string $resource = OrderResource::class;
}
