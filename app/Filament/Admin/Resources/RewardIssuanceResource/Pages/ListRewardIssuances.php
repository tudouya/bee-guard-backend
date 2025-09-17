<?php

namespace App\Filament\Admin\Resources\RewardIssuanceResource\Pages;

use App\Filament\Admin\Resources\RewardIssuanceResource;
use Filament\Resources\Pages\ListRecords;

class ListRewardIssuances extends ListRecords
{
    protected static string $resource = RewardIssuanceResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
