<?php

namespace App\Filament\Admin\Resources\CouponTemplateResource\Pages;

use App\Filament\Admin\Resources\CouponTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCouponTemplates extends ListRecords
{
    protected static string $resource = CouponTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('新建购物券模板')
                ->icon('heroicon-o-plus')
                ->visible(fn () => CouponTemplateResource::canCreate()),
        ];
    }
}
