<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\EpidemicBulletinResource\Pages;
use App\Models\EpidemicBulletin;
use App\Models\Region;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use BackedEnum;
use UnitEnum;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;

class EpidemicBulletinResource extends Resource
{
    protected static ?string $model = EpidemicBulletin::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-newspaper';

    protected static string|UnitEnum|null $navigationGroup = '疫情管理';

    protected static ?string $navigationLabel = '疫情通报';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = '疫情通报';

    protected static ?string $pluralModelLabel = '疫情通报';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('基础信息')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('标题')
                            ->required()
                            ->maxLength(200),
                        Forms\Components\TextInput::make('source')
                            ->label('信息来源')
                            ->maxLength(150),
                        Forms\Components\FileUpload::make('thumbnail_url')
                            ->label('缩略图')
                            ->image()
                            ->disk('public')
                            ->directory('epidemic-bulletins')
                            ->imageEditor()
                            ->maxSize(2048)
                            ->helperText('用于展示列表的缩略图，建议 4:3 或 16:9 比例，最大 2MB。')
                            ->columnSpanFull(),
                        Forms\Components\Select::make('risk_level')
                            ->label('风险等级')
                            ->options([
                                EpidemicBulletin::RISK_HIGH => '高风险',
                                EpidemicBulletin::RISK_MEDIUM => '中风险',
                                EpidemicBulletin::RISK_LOW => '低风险',
                            ])
                            ->default(EpidemicBulletin::RISK_LOW)
                            ->required()
                            ->native(false),
                        Forms\Components\Select::make('status')
                            ->label('发布状态')
                            ->options([
                                EpidemicBulletin::STATUS_DRAFT => '草稿',
                                EpidemicBulletin::STATUS_PUBLISHED => '已发布',
                            ])
                            ->default(EpidemicBulletin::STATUS_DRAFT)
                            ->required()
                            ->native(false),
                        Forms\Components\Toggle::make('homepage_featured')
                            ->label('推荐到首页')
                            ->helperText('开启后会在首页“疫情通报”区域优先展示。')
                            ->inline(false),
                        Forms\Components\DatePicker::make('published_at')
                            ->label('发布时间')
                            ->displayFormat('Y-m-d')
                            ->helperText('可选，默认使用当天日期（状态为“已发布”时）。'),
                        Forms\Components\Textarea::make('summary')
                            ->label('摘要')
                            ->rows(3)
                            ->maxLength(500)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('所属地区')
                    ->schema([
                        Forms\Components\Select::make('province_code')
                            ->label('省份')
                            ->options(fn () => Region::query()
                                ->whereColumn('province_code', 'code')
                                ->orderBy('code')
                                ->pluck('name', 'code')
                                ->toArray())
                            ->preload()
                            ->searchable()
                            ->reactive()
                            ->afterStateUpdated(function (callable $set) {
                                $set('city_code', null);
                                $set('district_code', null);
                            })
                            ->helperText('可选，不填表示全国范围。'),
                        Forms\Components\Select::make('city_code')
                            ->label('城市')
                            ->options(function (callable $get) {
                                $provinceCode = $get('province_code');
                                if (!$provinceCode) {
                                    return [];
                                }
                                return Region::query()
                                    ->where('province_code', $provinceCode)
                                    ->whereColumn('city_code', 'code')
                                    ->orderBy('code')
                                    ->pluck('name', 'code')
                                    ->toArray();
                            })
                            ->preload()
                            ->searchable()
                            ->reactive()
                            ->afterStateUpdated(function (callable $set) {
                                $set('district_code', null);
                            })
                            ->disabled(fn (callable $get) => !$get('province_code')),
                        Forms\Components\Select::make('district_code')
                            ->label('区县')
                            ->options(function (callable $get) {
                                $cityCode = $get('city_code');
                                if (!$cityCode) {
                                    return [];
                                }
                                return Region::query()
                                    ->where('city_code', $cityCode)
                                    ->where('code', '!=', $cityCode)
                                    ->orderBy('code')
                                    ->pluck('name', 'code')
                                    ->toArray();
                            })
                            ->searchable()
                            ->disabled(fn (callable $get) => !$get('city_code')),
                    ])
                    ->columns(3),
                Section::make('内容')
                    ->schema([
                        Forms\Components\RichEditor::make('content')
                            ->label('正文')
                            ->columnSpanFull()
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'strike',
                                'bulletList',
                                'orderedList',
                                'link',
                                'blockquote',
                                'codeBlock',
                                'redo',
                                'undo',
                            ])
                            ->required(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('标题')
                    ->limit(40)
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('risk_level')
                    ->label('风险等级')
                    ->colors([
                        'danger' => EpidemicBulletin::RISK_HIGH,
                        'warning' => EpidemicBulletin::RISK_MEDIUM,
                        'success' => EpidemicBulletin::RISK_LOW,
                    ])
                    ->formatStateUsing(function (string $state) {
                        return match ($state) {
                            EpidemicBulletin::RISK_HIGH => '高风险',
                            EpidemicBulletin::RISK_MEDIUM => '中风险',
                            default => '低风险',
                        };
                    })
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('状态')
                    ->colors([
                        'gray' => EpidemicBulletin::STATUS_DRAFT,
                        'success' => EpidemicBulletin::STATUS_PUBLISHED,
                    ])
                    ->formatStateUsing(function (string $state) {
                        return $state === EpidemicBulletin::STATUS_PUBLISHED ? '已发布' : '草稿';
                    })
                    ->sortable(),
                Tables\Columns\IconColumn::make('homepage_featured')
                    ->label('首页推荐')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-star')
                    ->trueColor('warning')
                    ->sortable(),
                Tables\Columns\TextColumn::make('published_at')
                    ->label('发布时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('更新时间')
                    ->since()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('risk_level')
                    ->label('风险等级')
                    ->options([
                        EpidemicBulletin::RISK_HIGH => '高风险',
                        EpidemicBulletin::RISK_MEDIUM => '中风险',
                        EpidemicBulletin::RISK_LOW => '低风险',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->label('状态')
                    ->options([
                        EpidemicBulletin::STATUS_DRAFT => '草稿',
                        EpidemicBulletin::STATUS_PUBLISHED => '已发布',
                    ]),
                Tables\Filters\TernaryFilter::make('homepage_featured')
                    ->label('首页推荐')
                    ->trueLabel('仅推荐到首页')
                    ->falseLabel('仅未推荐')
                    ->placeholder('全部'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('published_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEpidemicBulletins::route('/'),
            'create' => Pages\CreateEpidemicBulletin::route('/create'),
            'edit' => Pages\EditEpidemicBulletin::route('/{record}/edit'),
        ];
    }
}
