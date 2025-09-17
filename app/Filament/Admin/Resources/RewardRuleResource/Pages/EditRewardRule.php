<?php

namespace App\Filament\Admin\Resources\RewardRuleResource\Pages;

use App\Filament\Admin\Resources\RewardRuleResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditRewardRule extends EditRecord
{
    protected static string $resource = RewardRuleResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = Auth::id();

        return $data;
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return '奖励规则已更新';
    }
}
