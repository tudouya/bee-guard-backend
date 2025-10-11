<?php

namespace App\Filament\Inspector\Resources;

use App\Support\InspectorNavigation;
use App\Filament\Inspector\Resources\RewardIssuanceResource\Pages;

class RewardIssuanceResource extends \App\Filament\Admin\Resources\RewardIssuanceResource
{
    public static function getNavigationGroup(): ?string
    {
        return InspectorNavigation::GROUP_REWARDS;
    }

    public static function getNavigationSort(): ?int
    {
        return InspectorNavigation::ORDER_REWARD_ISSUANCES;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRewardIssuances::route('/'),
            'view' => Pages\ViewRewardIssuance::route('/{record}'),
        ];
    }
}
