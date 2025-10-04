<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\RecommendationRuleResource\Pages;
use App\Models\RecommendationRule;
use App\Support\AdminNavigation;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;
use Filament\Schemas\Components\Utilities\Set;

class RecommendationRuleResource extends Resource
{
    protected static ?string $model = RecommendationRule::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = '推荐规则';

    protected static \UnitEnum|string|null $navigationGroup = AdminNavigation::GROUP_RECOMMENDATION;

    protected static ?int $navigationSort = AdminNavigation::ORDER_RECOMMENDATION_RULES;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('范围与目标')->schema([
                Radio::make('scope_type')
                    ->label('适用范围')
                    ->options([
                        'global' => '平台通用',
                        'enterprise' => '企业专用',
                    ])
                    ->inline()
                    ->default('global')
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (string $state, Set $set, callable $get) {
                        // Set sensible tier defaults when switching scope if tier was untouched
                        $current = (int) ($get('tier') ?? 0);
                        if ($current === 0 || $current === 10 || $current === 20) {
                            $set('tier', $state === 'enterprise' ? 10 : 20);
                        }
                    }),

                Select::make('enterprise_id')
                    ->label('所属企业')
                    ->relationship('enterprise', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn (callable $get) => $get('scope_type') === 'enterprise')
                    ->required(fn (callable $get) => $get('scope_type') === 'enterprise'),

                Select::make('applies_to')
                    ->label('适用检测号')
                    ->options([
                        'self_paid' => '自费检测号',
                        'gift' => '企业赠送检测号',
                        'any' => '全部检测号',
                    ])
                    ->default('any')
                    ->required()
                    ->native(false),

                // 让下拉在更宽的列展示，避免换行
                Grid::make([
                    'default' => 1,
                    'md' => 2,
                    'lg' => 2,
                ])->schema([
                    Select::make('disease_id')
                        ->label('对应病种')
                        ->relationship('disease', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),

                    Select::make('product_id')
                        ->label('推荐产品')
                        ->relationship('product', 'name', fn ($query, $get) => $query
                            ->when($get('scope_type') === 'enterprise' && $get('enterprise_id'),
                                fn ($q) => $q->where('enterprise_id', $get('enterprise_id'))
                            )
                        )
                        ->searchable()
                        ->preload()
                        ->required(),

                    TextInput::make('priority')
                        ->label('排序优先级')
                        ->numeric()
                        ->default(0)
                        ->required(),
                ]),
            ])->columns(1),

            Section::make('排序与推广')->schema([
                Toggle::make('sponsored')
                    ->label('付费推广')
                    ->default(false)
                    ->helperText('标记为付费推广，可结合层级值实现平台置顶。'),
                TextInput::make('tier')
                    ->label('层级（数值越小越靠前）')
                    ->numeric()
                    ->default(fn (callable $get) => $get('scope_type') === 'enterprise' ? 10 : 20)
                    ->required()
                    ->minValue(0)
                    ->helperText('默认：企业 10、全局 20；数值越小展示位置越靠前。'),
            ])->columns(2),

            Section::make('启用与有效期')
                ->schema([
                    Toggle::make('active')
                        ->label('启用规则')
                        ->default(true),
                    Grid::make(2)->schema([
                        DatePicker::make('starts_at')->label('开始时间')->native(false),
                        DatePicker::make('ends_at')->label('结束时间')->native(false),
                    ]),
                ]),
            // 使用说明：为避免组件兼容性问题，这里用简要占位说明，详细说明见列表页「规则说明」按钮
            Section::make('使用说明（简要）')
                ->description('企业优先（默认：Enterprise Tier=10，全局 Tier=20）；赞助可将 Tier 调小以“置顶”。详细说明见列表页右上角「规则说明」。')
                ->schema([]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable()->toggleable(),
                TextColumn::make('scope_type')
                    ->label('适用范围')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'global' => '平台通用',
                        'enterprise' => '企业专用',
                        default => $state,
                    })
                    ->sortable(),
                TextColumn::make('applies_to')
                    ->label('适用检测号')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'self_paid' => '自费检测号',
                        'gift' => '企业赠送检测号',
                        'any' => '全部检测号',
                        default => $state,
                    })
                    ->sortable(),
                TextColumn::make('enterprise.name')->label('所属企业')->toggleable(),
                TextColumn::make('disease.name')->label('病种')->searchable()->sortable(),
                TextColumn::make('product.name')->label('推荐产品')->searchable()->sortable(),
                TextColumn::make('priority')->label('排序优先级')->sortable(),
                TextColumn::make('tier')->label('层级')->sortable(),
                TextColumn::make('sponsored')
                    ->label('付费推广')
                    ->badge()
                    ->formatStateUsing(fn ($s) => $s ? '是' : '否')
                    ->color(fn ($s) => $s ? 'warning' : 'gray'),
                TextColumn::make('active')
                    ->label('启用状态')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? '启用中' : '已停用'),
                TextColumn::make('starts_at')->label('开始时间')->date()->sortable(),
                TextColumn::make('ends_at')->label('结束时间')->date()->sortable(),
            ])
            ->filters([
                SelectFilter::make('scope_type')->label('适用范围')->options([
                    'global' => '平台通用',
                    'enterprise' => '企业专用',
                ]),
                SelectFilter::make('applies_to')->label('检测号类型')->options([
                    'self_paid' => '自费检测号',
                    'gift' => '企业赠送检测号',
                    'any' => '全部检测号',
                ]),
                SelectFilter::make('enterprise_id')->label('所属企业')->relationship('enterprise', 'name'),
                SelectFilter::make('disease_id')->label('病种')->relationship('disease', 'name'),
                SelectFilter::make('product_id')->label('推荐产品')->relationship('product', 'name'),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRecommendationRules::route('/'),
            'create' => Pages\CreateRecommendationRule::route('/create'),
            'edit' => Pages\EditRecommendationRule::route('/{record}/edit'),
        ];
    }
}
