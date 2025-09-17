<?php

namespace App\Filament\Admin\Resources\RewardIssuanceResource\Pages;

use App\Filament\Admin\Resources\RewardIssuanceResource;
use Filament\Resources\Pages\ViewRecord;

class ViewRewardIssuance extends ViewRecord
{
    protected static string $resource = RewardIssuanceResource::class;

    protected function getActions(): array
    {
        return [];
    }
}
