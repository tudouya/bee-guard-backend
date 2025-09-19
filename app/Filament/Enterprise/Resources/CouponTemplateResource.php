<?php

namespace App\Filament\Enterprise\Resources;

use App\Enums\CouponTemplateStatus;
use App\Filament\Enterprise\Resources\CouponTemplateResource\Pages;
use App\Models\CouponTemplate;
use App\Models\Enterprise;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;

class CouponTemplateResource extends Resource
{
    protected static ?string $model = CouponTemplate::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-ticket';
    }

    public static function getNavigationLabel(): string
    {
        return '购物券模板';
    }

    public static function getNavigationGroup(): ?string
    {
        return '奖励配置';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('券基础信息')
                ->description('提交后将进入平台审核，审核通过后方可用于奖励规则。')
                ->schema([
                    Select::make('enterprise_id')
                        ->label('所属企业')
                        ->options(fn () => self::getEnterpriseOptions())
                        ->required()
                        ->native(false)
                        ->default(fn () => array_key_first(self::getEnterpriseOptions()))
                        ->disabled(fn (?CouponTemplate $record) => filled($record))
                        ->helperText('若列表为空，请联系平台管理员为账号指派企业。'),
                    TextInput::make('title')
                        ->label('券名称')
                        ->required()
                        ->maxLength(191),
                    Select::make('platform')
                        ->label('发券平台')
                        ->required()
                        ->options(self::platformOptions())
                        ->native(false),
                    TextInput::make('store_name')
                        ->label('店铺名称')
                        ->required()
                        ->maxLength(191),
                    TextInput::make('store_url')
                        ->label('店铺链接')
                        ->url()
                        ->required()
                        ->maxLength(255)
                        ->helperText('请填写可访问的店铺或优惠券链接，便于蜂农快速跳转。'),
                    TextInput::make('face_value')
                        ->label('面值（元）')
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->step(0.01),
                    TextInput::make('total_quantity')
                        ->label('发放总量（留空表示不限）')
                        ->numeric()
                        ->nullable()
                        ->minValue(1),
                    DateTimePicker::make('valid_from')
                        ->label('有效期开始')
                        ->required()
                        ->seconds(false),
                    DateTimePicker::make('valid_until')
                        ->label('有效期结束')
                        ->required()
                        ->seconds(false)
                        ->afterOrEqual('valid_from'),
                    Textarea::make('usage_instructions')
                        ->label('使用说明')
                        ->rows(5)
                        ->required()
                        ->columnSpanFull(),
                ])->columns([
                    'default' => 1,
                    'md' => 2,
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('券名称')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('platform')
                    ->label('平台')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => self::platformLabels()[$state] ?? $state)
                    ->color(fn (?string $state) => match ($state) {
                        'jd' => 'info',
                        'taobao' => 'warning',
                        'pinduoduo' => 'pink',
                        'offline' => 'gray',
                        default => 'secondary',
                    }),
                TextColumn::make('store_name')
                    ->label('店铺')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->formatStateUsing(fn (?CouponTemplateStatus $state) => $state ? self::statusLabels()[$state->value] : null)
                    ->color(fn (?CouponTemplateStatus $state) => match ($state) {
                        CouponTemplateStatus::PendingReview => 'warning',
                        CouponTemplateStatus::Approved => 'success',
                        CouponTemplateStatus::Rejected => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('valid_from')
                    ->label('生效日期')
                    ->date(),
                TextColumn::make('valid_until')
                    ->label('截止日期')
                    ->date(),
                TextColumn::make('updated_at')
                    ->label('更新时间')
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('状态筛选')
                    ->options(self::statusLabels())
                    ->native(false),
            ])
            ->actions([
                EditAction::make()
                    ->label('重新提交')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (CouponTemplate $record) => self::canEditRecord($record)),
                ViewAction::make()
                    ->label('详情'),
                DeleteAction::make()
                    ->visible(fn (CouponTemplate $record) => self::canDeleteRecord($record)),
            ])
            ->bulkActions([])
            ->modifyQueryUsing(fn (Builder $query) => $query->latest());
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCouponTemplates::route('/'),
            'create' => Pages\CreateCouponTemplate::route('/create'),
            'edit' => Pages\EditCouponTemplate::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();
        $isSuper = $user && (string) $user->role === 'super_admin';
        if (! $isSuper) {
            $userId = $user?->getAuthIdentifier();
            if ($userId) {
                $query->where('submitted_by', $userId);
            } else {
                $query->whereRaw('1=0'); // 未登录保护
            }
        }

        return $query;
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        if ($user && (string) $user->role === 'super_admin') {
            return true;
        }
        return filled(self::getEnterpriseOptions());
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();
        $isSuper = $user && (string) $user->role === 'super_admin';
        if ($isSuper) {
            return parent::canEdit($record) && self::canEditRecord($record);
        }

        // 企业用户仅可编辑：自身提交、且记录企业归属自己，且状态允许
        $ownSubmit = (int) ($record->submitted_by ?? 0) === (int) ($user?->getAuthIdentifier() ?? 0);
        $ownEnterprise = (int) optional($record->enterprise)->owner_user_id === (int) ($user?->getAuthIdentifier() ?? 0);
        return parent::canEdit($record) && self::canEditRecord($record) && $ownSubmit && $ownEnterprise;
    }

    public static function canDelete(Model $record): bool
    {
        $user = auth()->user();
        $isSuper = $user && (string) $user->role === 'super_admin';
        if ($isSuper) {
            return parent::canDelete($record) && self::canDeleteRecord($record);
        }

        $ownSubmit = (int) ($record->submitted_by ?? 0) === (int) ($user?->getAuthIdentifier() ?? 0);
        $ownEnterprise = (int) optional($record->enterprise)->owner_user_id === (int) ($user?->getAuthIdentifier() ?? 0);
        return parent::canDelete($record) && self::canDeleteRecord($record) && $ownSubmit && $ownEnterprise;
    }

    protected static function getEnterpriseOptions(): array
    {
        /** @var Authenticatable|null $user */
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        $isSuper = (string) $user->role === 'super_admin';
        $query = Enterprise::query();
        if (! $isSuper) {
            $query->where('owner_user_id', $user->getAuthIdentifier());
        }

        return $query->orderBy('name')->pluck('name', 'id')->toArray();
    }

    protected static function platformOptions(): array
    {
        return [
            'jd' => '京东',
            'taobao' => '淘宝',
            'pinduoduo' => '拼多多',
            'offline' => '线下门店',
            'other' => '其他平台',
        ];
    }

    protected static function platformLabels(): array
    {
        return self::platformOptions();
    }

    protected static function statusLabels(): array
    {
        return [
            CouponTemplateStatus::Draft->value => '草稿',
            CouponTemplateStatus::PendingReview->value => '待审核',
            CouponTemplateStatus::Approved->value => '已通过',
            CouponTemplateStatus::Rejected->value => '已驳回',
        ];
    }

    public static function canEditRecord(CouponTemplate $record): bool
    {
        return in_array($record->status, [
            CouponTemplateStatus::Draft,
            CouponTemplateStatus::Rejected,
        ], true);
    }

    public static function canDeleteRecord(CouponTemplate $record): bool
    {
        return in_array($record->status, [
            CouponTemplateStatus::Draft,
            CouponTemplateStatus::Rejected,
        ], true);
    }
}
