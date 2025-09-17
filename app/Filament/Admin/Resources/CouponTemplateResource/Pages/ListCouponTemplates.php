<?php

namespace App\Filament\Admin\Resources\CouponTemplateResource\Pages;

use App\Filament\Admin\Resources\CouponTemplateResource;
use Filament\Resources\Pages\ListRecords;

class ListCouponTemplates extends ListRecords
{
    protected static string $resource = CouponTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
