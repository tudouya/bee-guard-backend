<?php

namespace App\Filament\Admin\Resources\CouponTemplateResource\Pages;

use App\Enums\CouponTemplateStatus;
use App\Filament\Admin\Resources\CouponTemplateResource;
use App\Filament\Forms\CouponTemplateForm;
use App\Models\Enterprise;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class EditCouponTemplate extends EditRecord
{
    protected static string $resource = CouponTemplateResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema->schema(
            CouponTemplateForm::make([
                'description' => '保存后将直接生效，请在提交前确认信息。',
                'platformOptions' => CouponTemplateResource::platformLabels(),
                'enterpriseField' => [
                    'options' => fn () => Enterprise::query()->orderBy('name')->pluck('name', 'id')->toArray(),
                    'placeholder' => '请选择所属企业',
                    'helperText' => '修改模板会覆盖企业侧配置，请谨慎操作。',
                    'native' => false,
                    'searchable' => true,
                    'preload' => true,
                ],
            ])
        );
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $userId = Auth::id();

        if (empty($data['submitted_by'])) {
            $data['submitted_by'] = $this->getRecord()->submitted_by ?? $userId;
        }

        $data['status'] = CouponTemplateStatus::Approved->value;
        $data['rejection_reason'] = null;
        $data['reviewed_by'] = $userId;
        $data['reviewed_at'] = now();

        return $data;
    }

    protected function authorizeAccess(): void
    {
        parent::authorizeAccess();

        abort_unless(CouponTemplateResource::canEdit($this->getRecord()), 403);
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return '购物券模板已更新并生效';
    }
}
