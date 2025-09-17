<?php

namespace App\Filament\Admin\Resources\RewardRuleResource\Pages;

use App\Filament\Admin\Resources\RewardRuleResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateRewardRule extends CreateRecord
{
    protected static string $resource = RewardRuleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();

        return $data;
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return '奖励规则已创建';
    }
}
