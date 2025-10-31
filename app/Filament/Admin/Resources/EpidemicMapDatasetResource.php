<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\EpidemicMapDatasetResource\Pages;
use App\Models\Disease;
use App\Models\EpidemicMapDataset;
use App\Models\Region;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

class EpidemicMapDatasetResource extends Resource
{
    protected static ?string $model = EpidemicMapDataset::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-pie';

    protected static ?string $navigationLabel = '疫情地图数据';

    protected static string|\UnitEnum|null $navigationGroup = '疫情管理';

    protected static ?int $navigationSort = 35;

    protected static ?string $modelLabel = '疫情地图数据';

    protected static ?string $pluralModelLabel = '疫情地图数据';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('地区与时间')
                ->schema([
                    TextInput::make('year')
                        ->label('统计年份')
                        ->numeric()
                        ->minValue(2000)
                        ->maxValue(2100)
                        ->required(),
                    Select::make('province_code')
                        ->label('省份')
                        ->searchable()
                        ->native(false)
                        ->options(fn () => Region::query()
                            ->whereColumn('province_code', 'code')
                            ->orderBy('code')
                            ->pluck('name', 'code')
                            ->toArray())
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(function (?string $state, Set $set) {
                            $set('district_code', null);
                            $set('city_code', null);
                        }),
                    Select::make('district_code')
                        ->label('区县')
                        ->searchable()
                        ->native(false)
                        ->options(function (callable $get) {
                            $provinceCode = $get('province_code');
                            if (!$provinceCode) {
                                return [];
                            }

                            return Region::query()
                                ->where('province_code', $provinceCode)
                                ->whereNotNull('city_code')
                                ->whereColumn('city_code', '!=', 'code')
                                ->orderBy('code')
                                ->pluck('name', 'code')
                                ->toArray();
                        })
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(function (?string $state, Set $set) {
                            if (!$state) {
                                $set('city_code', null);
                                return;
                            }

                            $region = Region::query()->where('code', $state)->first();
                            $set('city_code', $region?->city_code);
                        }),
                    Hidden::make('city_code'),
                    DatePicker::make('data_updated_at')
                        ->label('数据更新时间')
                        ->native(false)
                        ->displayFormat('Y-m-d')
                        ->format('Y-m-d')
                        ->helperText('默认保存时自动填写当前日期，可手动调整。'),
                    TextInput::make('source')
                        ->label('数据来源')
                        ->maxLength(191),
                    Textarea::make('notes')
                        ->label('备注说明')
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Select::make('source_type')
                        ->label('数据类型')
                        ->options([
                            'manual' => '人工录入',
                            'auto' => '检测记录自动生成',
                        ])
                        ->default('manual')
                        ->disabled(fn (?EpidemicMapDataset $record) => $record?->source_type === 'auto')
                        ->dehydrated(fn ($state, ?EpidemicMapDataset $record) => $record?->source_type !== 'auto')
                        ->helperText('自动生成的数据来源不可修改。'),
                    Toggle::make('locked')
                        ->label('锁定数据')
                        ->default(false)
                        ->helperText('锁定后禁止自动聚合更新（仅人工录入时可用）'),
                ])
                ->columns(2),

            Section::make('月度病害数据')
                ->schema([
                    Repeater::make('entries')
                        ->relationship('entries')
                        ->orderable(false)
                        ->defaultItems(0)
                        ->collapsible()
                        ->collapsed(false)
                        ->itemLabel(function (array $state) {
                            $month = Arr::get($state, 'month');
                            $disease = Arr::get($state, 'disease_code');
                            if (!$month || !$disease) {
                                return '未命名记录';
                            }

                            $monthLabel = sprintf('%02d月', (int) $month);
                            return $monthLabel . ' · ' . $disease;
                        })
                        ->schema([
                            Select::make('month')
                                ->label('月份')
                                ->options(self::monthOptions())
                                ->required()
                                ->native(false),
                            Select::make('disease_code')
                                ->label('病种')
                                ->searchable()
                                ->native(false)
                                ->options(fn () => Disease::query()
                                    ->orderBy('map_order')
                                    ->orderBy('sort')
                                    ->orderBy('id')
                                    ->pluck('name', 'code')
                                    ->toArray())
                                ->required(),
                            TextInput::make('positive_cases')
                                ->label('阳性数量')
                                ->numeric()
                                ->minValue(0)
                                ->required(),
                            TextInput::make('sample_total')
                                ->label('样本总数')
                                ->numeric()
                                ->minValue(0)
                                ->required()
                                ->rule(function (callable $get) {
                                    return function (string $attribute, $value, \Closure $fail) use ($get) {
                                        $positive = (int) ($get('positive_cases') ?? 0);
                                        $total = (int) $value;
                                        if ($total < $positive) {
                                            $fail('样本总数需大于或等于阳性数量');
                                        }
                                    };
                                })
                                ->helperText('需大于等于阳性数量'),
                            Textarea::make('remark')
                                ->label('备注')
                                ->rows(1)
                                ->maxLength(255)
                                ->columnSpanFull(),
                        ])->columns(2)
                        ->columnSpanFull(),
                ])
                ->columns(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('entries'))
            ->columns([
                TextColumn::make('id')->label('ID')->sortable()->toggleable(),
                TextColumn::make('year')->label('年份')->sortable(),
                TextColumn::make('province_code')
                    ->label('省份')
                    ->formatStateUsing(fn (?string $state) => self::resolveRegionName($state))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('district_code')
                    ->label('区县')
                    ->formatStateUsing(fn (?string $state) => self::resolveRegionName($state))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('entries_count')
                    ->label('记录数')
                    ->sortable(),
                TextColumn::make('source_type')
                    ->label('数据类型')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'auto' ? '自动' : '人工')
                    ->colors([
                        'secondary' => 'manual',
                        'warning' => 'auto',
                    ])
                    ->sortable(),
                TextColumn::make('locked')
                    ->label('锁定')
                    ->badge()
                    ->formatStateUsing(fn (bool $state) => $state ? '是' : '否')
                    ->colors([
                        'success' => true,
                        'gray' => false,
                    ])
                    ->sortable(),
                TextColumn::make('data_updated_at')
                    ->label('数据时间')
                    ->date('Y-m-d')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('更新时间')
                    ->date('Y-m-d')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('year')
                    ->label('年份')
                    ->options(fn () => EpidemicMapDataset::query()
                        ->select('year')
                        ->distinct()
                        ->orderByDesc('year')
                        ->pluck('year', 'year')
                        ->toArray()),
                SelectFilter::make('province_code')
                    ->label('省份')
                    ->options(fn () => Region::query()
                        ->whereColumn('province_code', 'code')
                        ->orderBy('code')
                        ->pluck('name', 'code')
                        ->toArray()),
                SelectFilter::make('district_code')
                    ->label('区县')
                    ->searchable()
                    ->options(function () {
                        return Region::query()
                            ->whereColumn('city_code', '!=', 'code')
                            ->whereNotNull('city_code')
                            ->orderBy('code')
                            ->pluck('name', 'code')
                            ->toArray();
                    }),
                SelectFilter::make('source_type')
                    ->label('数据类型')
                    ->options([
                        'manual' => '人工录入',
                        'auto' => '检测记录自动生成',
                    ]),
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
            'index' => Pages\ListEpidemicMapDatasets::route('/'),
            'create' => Pages\CreateEpidemicMapDataset::route('/create'),
            'edit' => Pages\EditEpidemicMapDataset::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [];
    }

    private static function monthOptions(): array
    {
        $options = [];
        for ($i = 1; $i <= 12; $i++) {
            $options[$i] = sprintf('%02d 月', $i);
        }
        return $options;
    }

    private static function resolveRegionName(?string $code): string
    {
        if (!$code) {
            return '-';
        }

        static $cache = [];
        if (!array_key_exists($code, $cache)) {
            $cache[$code] = Region::query()->where('code', $code)->value('name') ?? $code;
        }

        return $cache[$code];
    }
}
