<?php

namespace App\Filament\Enterprise\Resources\CouponTemplateResource\Pages;

use App\Enums\CouponTemplateStatus;
use App\Filament\Enterprise\Resources\CouponTemplateResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditCouponTemplate extends EditRecord
{
    protected static string $resource = CouponTemplateResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['submitted_by'] = Auth::id();
        $data['status'] = CouponTemplateStatus::PendingReview->value;
        $data['rejection_reason'] = null;
        $data['reviewed_by'] = null;
        $data['reviewed_at'] = null;

        return $data;
    }

    protected function authorizeAccess(): void
    {
        parent::authorizeAccess();

        // 使用资源层的综合权限判断，防止 URL 直达越权
        abort_unless(CouponTemplateResource::canEdit($this->getRecord()), 403);
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return '购物券模板已重新提交审核';
    }
}
