<?php

namespace App\Filament\Inspector\Resources\ShippingNotificationResource\Pages;

use App\Filament\Inspector\Resources\ShippingNotificationResource;
use App\Filament\Admin\Resources\ShippingNotificationResource\Pages\ListShippingNotifications as BaseListShippingNotifications;

class ListShippingNotifications extends BaseListShippingNotifications
{
    protected static string $resource = ShippingNotificationResource::class;
}
