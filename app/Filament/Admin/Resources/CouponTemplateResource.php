<?php

namespace App\Filament\Admin\Resources;

use App\Enums\CouponTemplateStatus;
use App\Filament\Admin\Resources\CouponTemplateResource\Pages;
use App\Models\CouponTemplate;
use App\Support\AdminNavigation;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CouponTemplateResource extends Resource
{
    protected static ?string $model = CouponTemplate::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-clipboard-document-check';
    }

    public static function getNavigationLabel(): string
    {
        return '购物券模板审核';
    }

    public static function getNavigationGroup(): ?string
    {
        return AdminNavigation::GROUP_REWARDS;
    }

    public static function getNavigationSort(): ?int
    {
        return AdminNavigation::ORDER_COUPON_TEMPLATES;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('企业与平台信息')
                ->schema([
                    Select::make('enterprise_id')
                        ->label('所属企业')
                        ->relationship('enterprise', 'name')
                        ->native(false)
                        ->disabled(),
                    TextInput::make('title')
                        ->label('券名称')
                        ->disabled(),
                    Select::make('platform')
                        ->label('发券平台')
                        ->options(self::platformLabels())
                        ->native(false)
                        ->disabled(),
                    TextInput::make('store_name')
                        ->label('店铺名称')
                        ->disabled(),
                    TextInput::make('store_url')
                        ->label('店铺链接')
                        ->url()
                        ->disabled(),
                ])->columns([
                    'default' => 1,
                    'md' => 2,
                ]),

            Section::make('券属性')
                ->schema([
                    TextInput::make('face_value')
                        ->label('面值（元）')
                        ->numeric()
                        ->disabled(),
                    TextInput::make('total_quantity')
                        ->label('发放总量')
                        ->numeric()
                        ->disabled(),
                    DatePicker::make('valid_from')
                        ->label('有效期开始')
                        ->native(false)
                        ->disabled(),
                    DatePicker::make('valid_until')
                        ->label('有效期结束')
                        ->native(false)
                        ->disabled(),
                    Placeholder::make('status_label')
                        ->label('状态')
                        ->content(fn (CouponTemplate $record) => self::statusLabels()[$record->status->value] ?? $record->status?->value ?? '未知'),
                ])->columns([
                    'default' => 1,
                    'md' => 2,
                ]),

            Section::make('使用说明')
                ->schema([
                    Textarea::make('usage_instructions')
                        ->rows(5)
                        ->disabled(),
                ]),

            Section::make('驳回原因')
                ->schema([
                    Textarea::make('rejection_reason')
                        ->rows(4)
                        ->disabled()
                        ->visible(fn (CouponTemplate $record) => $record->status === CouponTemplateStatus::Rejected),
                ])->visible(fn (CouponTemplate $record) => $record->status === CouponTemplateStatus::Rejected),

            Section::make('审核信息')
                ->schema([
                    Placeholder::make('submitted_by')
                        ->label('提交人')
                        ->content(fn (CouponTemplate $record) => optional($record->submitter)->display_name ?? '-'),
                    Placeholder::make('reviewed_by')
                        ->label('审核人')
                        ->content(fn (CouponTemplate $record) => optional($record->reviewer)->display_name ?? '-'),
                    Placeholder::make('reviewed_at')
                        ->label('审核时间')
                        ->content(fn (CouponTemplate $record) => optional($record->reviewed_at)?->format('Y-m-d H:i') ?? '-'),
                ])->columns([
                    'default' => 1,
                    'md' => 3,
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
                TextColumn::make('enterprise.name')
                    ->label('企业')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('platform')
                    ->label('平台')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => self::platformLabels()[$state] ?? $state),
                TextColumn::make('submitter.display_name')
                    ->label('提交人')
                    ->toggleable(),
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
                TextColumn::make('valid_until')
                    ->label('有效期结束')
                    ->date()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('最后更新')
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('状态')
                    ->options(self::statusLabels())
                    ->native(false),
                SelectFilter::make('enterprise_id')
                    ->label('企业')
                    ->relationship('enterprise', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),
            ])
            ->actions([
                EditAction::make()
                    ->label('编辑')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (CouponTemplate $record) => static::canEdit($record)),
                ViewAction::make()
                    ->label('查看'),
                Action::make('approve')
                    ->label('通过')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (CouponTemplate $record) => $record->status === CouponTemplateStatus::PendingReview)
                    ->action(function (CouponTemplate $record): void {
                        $record->update([
                            'status' => CouponTemplateStatus::Approved,
                            'rejection_reason' => null,
                            'reviewed_by' => auth()->id(),
                            'reviewed_at' => now(),
                        ]);
                    }),
                Action::make('reject')
                    ->label('驳回')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (CouponTemplate $record) => $record->status === CouponTemplateStatus::PendingReview)
                    ->form([
                        Textarea::make('reason')
                            ->label('驳回原因')
                            ->rows(4)
                            ->required(),
                    ])
                    ->modalSubmitActionLabel('确认驳回')
                    ->action(function (CouponTemplate $record, array $data): void {
                        $record->update([
                            'status' => CouponTemplateStatus::Rejected,
                            'rejection_reason' => $data['reason'],
                            'reviewed_by' => auth()->id(),
                            'reviewed_at' => now(),
                        ]);
                    }),
                DeleteAction::make()
                    ->visible(fn (CouponTemplate $record) => (string) auth()->user()?->role === 'super_admin'
                        && $record->status !== CouponTemplateStatus::Approved),
            ])
            ->bulkActions([])
            ->modifyQueryUsing(fn (Builder $query) => $query->latest('created_at'));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCouponTemplates::route('/'),
            'create' => Pages\CreateCouponTemplate::route('/create'),
            'view' => Pages\ViewCouponTemplate::route('/{record}'),
            'edit' => Pages\EditCouponTemplate::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return (string) auth()->user()?->role === 'super_admin';
    }

    public static function canEdit($record): bool
    {
        return (string) auth()->user()?->role === 'super_admin'
            && $record instanceof CouponTemplate;
    }

    public static function platformLabels(): array
    {
        return [
            'jd' => '京东',
            'taobao' => '淘宝',
            'pinduoduo' => '拼多多',
            'offline' => '线下门店',
            'other' => '其他平台',
        ];
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
}
