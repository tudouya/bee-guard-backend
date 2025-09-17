<?php

namespace App\Filament\Enterprise\Resources\CouponTemplateResource\Pages;

use App\Enums\CouponTemplateStatus;
use App\Filament\Enterprise\Resources\CouponTemplateResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateCouponTemplate extends CreateRecord
{
    protected static string $resource = CouponTemplateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['submitted_by'] = Auth::id();
        $data['status'] = CouponTemplateStatus::PendingReview->value;
        $data['rejection_reason'] = null;
        $data['reviewed_by'] = null;
        $data['reviewed_at'] = null;

        return $data;
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return '购物券模板已提交审核';
    }
}
