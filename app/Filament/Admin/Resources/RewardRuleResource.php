<?php

namespace App\Filament\Admin\Resources;

use App\Enums\RewardComparator;
use App\Enums\RewardFulfillmentMode;
use App\Enums\RewardMetric;
use App\Filament\Admin\Resources\RewardRuleResource\Pages;
use App\Models\CouponTemplate;
use App\Models\RewardRule;
use App\Support\AdminNavigation;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RewardRuleResource extends Resource
{
    protected static ?string $model = RewardRule::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-adjustments-horizontal';
    }

    public static function getNavigationLabel(): string
    {
        return '奖励规则';
    }

    public static function getNavigationGroup(): ?string
    {
        return AdminNavigation::GROUP_REWARDS;
    }

    public static function getNavigationSort(): ?int
    {
        return AdminNavigation::ORDER_REWARD_RULES;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('规则条件')
                ->schema([
                    TextInput::make('name')
                        ->label('规则名称')
                        ->required()
                        ->maxLength(191),
                    Grid::make([
                        'default' => 1,
                        'md' => 3,
                    ])->schema([
                        Select::make('metric')
                            ->label('指标类型')
                            ->options(self::metricLabels())
                            ->native(false)
                            ->required()
                            ->default(RewardMetric::Likes->value),
                        Select::make('comparator')
                            ->label('比较符')
                            ->options(self::comparatorLabels())
                            ->native(false)
                            ->required()
                            ->default(RewardComparator::GreaterThanOrEqual->value),
                        TextInput::make('threshold')
                            ->label('阈值')
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->default(100),
                    ]),
                    Select::make('fulfillment_mode')
                        ->label('发放模式')
                        ->options(self::fulfillmentModeLabels())
                        ->native(false)
                        ->required()
                        ->live()
                        ->default(RewardFulfillmentMode::Automatic->value)
                        ->helperText('自动：符合条件立即发放；手动：进入待发放队列，由管理员二次确认。'),
                ]),

            Section::make('奖励内容')
                ->schema([
                    Select::make('coupon_template_id')
                        ->label('购物券模板')
                        ->relationship('couponTemplate', 'title', fn (Builder $query) => $query->approved())
                        ->getOptionLabelFromRecordUsing(fn (CouponTemplate $record) => sprintf('%s（%s）', $record->title, self::platformLabel($record->platform)))
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->helperText('仅显示已通过审核的购物券模板。')
                        ->nullable(),
                    TextInput::make('badge_type')
                        ->label('勋章标识（选填）')
                        ->maxLength(191)
                        ->placeholder('例如 elite_farmer、gold_badge')
                        ->nullable(),
                    Toggle::make('lecturer_program')
                        ->label('加入优质讲师团')
                        ->inline(false)
                        ->default(false),
                ])->columns([
                    'default' => 1,
                    'md' => 2,
                ]),

            Section::make('状态与说明')
                ->schema([
                    Toggle::make('is_active')
                        ->label('启用规则')
                        ->default(true),
                    Placeholder::make('manual_notice')
                        ->label('提示')
                        ->content('手动发放的奖励会进入待发放列表，需管理员确认后蜂农才能收到。')
                        ->visible(fn (callable $get) => $get('fulfillment_mode') === RewardFulfillmentMode::Manual->value)
                        ->columnSpanFull(),
                    Grid::make([
                        'default' => 1,
                        'md' => 2,
                    ])->schema([
                        Placeholder::make('created_by_display')
                            ->label('创建人')
                            ->content(fn (?RewardRule $record) => optional($record?->createdBy)->display_name ?? '保存后显示'),
                        Placeholder::make('updated_at_display')
                            ->label('最近更新')
                            ->content(fn (?RewardRule $record) => $record?->updated_at?->format('Y-m-d H:i') ?? '保存后显示'),
                    ])->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('规则名称')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('metric')
                    ->label('指标')
                    ->badge()
                    ->formatStateUsing(fn (?RewardMetric $state) => $state ? self::metricLabels()[$state->value] : $state?->value),
                TextColumn::make('threshold')
                    ->label('阈值')
                    ->sortable(),
                TextColumn::make('fulfillment_mode')
                    ->label('发放模式')
                    ->badge()
                    ->formatStateUsing(fn (?RewardFulfillmentMode $state) => $state ? self::fulfillmentModeLabels()[$state->value] : $state?->value),
                TextColumn::make('couponTemplate.title')
                    ->label('购物券模板')
                    ->toggleable(),
                ToggleColumn::make('is_active')
                    ->label('启用')
                    ->onColor('success')
                    ->offColor('gray')
                    ->updateStateUsing(function (RewardRule $record, bool $state): bool {
                        $record->update([
                            'is_active' => $state,
                            'updated_by' => auth()->id(),
                        ]);

                        return $state;
                    }),
                TextColumn::make('updated_at')
                    ->label('更新于')
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('metric')
                    ->label('指标')
                    ->options(self::metricLabels())
                    ->native(false),
                SelectFilter::make('fulfillment_mode')
                    ->label('发放模式')
                    ->options(self::fulfillmentModeLabels())
                    ->native(false),
                TernaryFilter::make('is_active')
                    ->label('启用状态')
                    ->boolean()
                    ->trueLabel('已启用')
                    ->falseLabel('已停用'),
            ])
            ->actions([
                EditAction::make()->label('编辑'),
                DeleteAction::make()->label('删除'),
            ])
            ->bulkActions([])
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['couponTemplate']));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRewardRules::route('/'),
            'create' => Pages\CreateRewardRule::route('/create'),
            'edit' => Pages\EditRewardRule::route('/{record}/edit'),
        ];
    }

    protected static function metricLabels(): array
    {
        return [
            RewardMetric::Likes->value => '点赞数',
            RewardMetric::Views->value => '浏览量',
            RewardMetric::Replies->value => '回复数',
        ];
    }

    protected static function comparatorLabels(): array
    {
        return [
            RewardComparator::GreaterThanOrEqual->value => '≥ (大于等于)',
        ];
    }

    protected static function fulfillmentModeLabels(): array
    {
        return [
            RewardFulfillmentMode::Automatic->value => '自动发放',
            RewardFulfillmentMode::Manual->value => '人工审核后发放',
        ];
    }

    protected static function platformLabel(?string $value): string
    {
        return match ($value) {
            'jd' => '京东',
            'taobao' => '淘宝',
            'pinduoduo' => '拼多多',
            'offline' => '线下',
            'other' => '其他',
            default => '未知平台',
        };
    }
}
