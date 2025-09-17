<?php

namespace App\Filament\Admin\Resources\CouponTemplateResource\Pages;

use App\Filament\Admin\Resources\CouponTemplateResource;
use Filament\Resources\Pages\ViewRecord;

class ViewCouponTemplate extends ViewRecord
{
    protected static string $resource = CouponTemplateResource::class;

    protected function getActions(): array
    {
        return [];
    }
}
