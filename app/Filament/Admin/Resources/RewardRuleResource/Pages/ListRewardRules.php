<?php

namespace App\Filament\Admin\Resources\RewardRuleResource\Pages;

use App\Filament\Admin\Resources\RewardRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRewardRules extends ListRecords
{
    protected static string $resource = RewardRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('新建奖励规则')
                ->icon('heroicon-o-plus'),
        ];
    }
}
