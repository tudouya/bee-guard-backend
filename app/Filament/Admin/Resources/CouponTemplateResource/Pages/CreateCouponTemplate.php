<?php

namespace App\Filament\Admin\Resources\CouponTemplateResource\Pages;

use App\Enums\CouponTemplateStatus;
use App\Filament\Admin\Resources\CouponTemplateResource;
use App\Filament\Forms\CouponTemplateForm;
use App\Models\Enterprise;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Auth;

class CreateCouponTemplate extends CreateRecord
{
    protected static string $resource = CouponTemplateResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns([
                'default' => 1,
                'lg' => 12,
            ])
            ->schema(
                CouponTemplateForm::make([
                    'description' => '创建后将立即生效，并可在奖励规则中使用。',
                    'platformOptions' => CouponTemplateResource::platformLabels(),
                    'enterpriseField' => [
                        'options' => fn () => Enterprise::query()->orderBy('name')->pluck('name', 'id')->toArray(),
                        'placeholder' => '请选择所属企业',
                        'helperText' => '管理员创建的模板会直接归属于所选企业。',
                        'native' => false,
                        'searchable' => true,
                        'preload' => true,
                    ],
                    'sectionColumnSpan' => [
                        'default' => 1,
                        'lg' => 8,
                    ],
                ])
            );
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $userId = Auth::id();

        $data['submitted_by'] = $userId;
        $data['status'] = CouponTemplateStatus::Approved->value;
        $data['rejection_reason'] = null;
        $data['reviewed_by'] = $userId;
        $data['reviewed_at'] = now();

        return $data;
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return '购物券模板已创建并生效';
    }
}
