<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Support\AdminNavigation;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Grid;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationLabel = '推荐产品';

    protected static \UnitEnum|string|null $navigationGroup = AdminNavigation::GROUP_RECOMMENDATION;

    protected static ?int $navigationSort = AdminNavigation::ORDER_PRODUCTS;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('基本信息')
                ->schema([
                    Select::make('enterprise_id')
                        ->label('所属企业')
                        ->relationship('enterprise', 'name')
                        ->required()
                        ->searchable()
                        ->preload(),
                    TextInput::make('name')
                        ->label('产品名称')
                        ->required()
                        ->maxLength(191),
                    TextInput::make('url')
                        ->label('产品链接')
                        ->maxLength(512)
                        ->url(),
                    Select::make('status')
                        ->label('状态')
                        ->options([
                            'active' => '上架',
                            'inactive' => '下架',
                        ])
                        ->required()
                        ->native(false),
                ])->columns(2),

            Section::make('内容与媒体')
                ->schema([
                    Textarea::make('brief')->label('简介')->rows(4),
                    Textarea::make('media')
                        ->label('媒体配置（JSON）')
                        ->rows(4)
                        ->helperText('可选，JSON 结构：例如 {"images": ["..."], "video": "..."}')
                        ->afterStateHydrated(function ($component, $state) {
                            if (is_array($state)) {
                                $component->state(json_encode($state, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
                            }
                        })
                        ->dehydrateStateUsing(function ($state) {
                            if (is_string($state) && $state !== '') {
                                try { return json_decode($state, true, 512, JSON_THROW_ON_ERROR); }
                                catch (\Throwable) { return null; }
                            }
                            return null;
                        }),
                ]),

            Section::make('首页推荐设置')
                ->schema([
                    Toggle::make('homepage_featured')
                        ->label('作为首页推荐展示')
                        ->helperText('开启后将在小程序首页产品推荐入口展示。')
                        ->default(false)
                        ->live(),
                    Grid::make([
                        'default' => 1,
                        'md' => 2,
                    ])->schema([
                        TextInput::make('homepage_sort_order')
                            ->label('首页排序值')
                            ->numeric()
                            ->default(0)
                            ->helperText('数值越小越靠前。')
                            ->visible(fn (callable $get) => (bool) $get('homepage_featured')),
                        TextInput::make('homepage_registration_no')
                            ->label('注册证号')
                            ->maxLength(191)
                            ->placeholder('例如：国械注准 2025-123456')
                            ->visible(fn (callable $get) => (bool) $get('homepage_featured')),
                        Textarea::make('homepage_applicable_scene')
                            ->label('适用场景')
                            ->rows(3)
                            ->helperText('每行填写一个场景，例如：春繁预防 / 夏季高温调理。')
                            ->visible(fn (callable $get) => (bool) $get('homepage_featured')),
                        Textarea::make('homepage_highlights')
                            ->label('产品亮点')
                            ->rows(3)
                            ->helperText('每行填写一条亮点。')
                            ->visible(fn (callable $get) => (bool) $get('homepage_featured')),
                        Textarea::make('homepage_cautions')
                            ->label('注意事项')
                            ->rows(3)
                            ->helperText('每行填写一条注意事项。')
                            ->visible(fn (callable $get) => (bool) $get('homepage_featured')),
                        TextInput::make('homepage_price')
                            ->label('产品价格')
                            ->maxLength(128)
                            ->placeholder('例如：¥199/套')
                            ->visible(fn (callable $get) => (bool) $get('homepage_featured')),
                    ])->columnSpanFull(),
                    Grid::make([
                        'default' => 1,
                        'md' => 2,
                    ])->schema([
                        TextInput::make('homepage_contact_company')
                            ->label('咨询企业名称')
                            ->maxLength(191)
                            ->placeholder('例如：蜂卫士生物科技有限公司')
                            ->visible(fn (callable $get) => (bool) $get('homepage_featured')),
                        TextInput::make('homepage_contact_phone')
                            ->label('联系电话')
                            ->tel()
                            ->maxLength(64)
                            ->placeholder('例如：400-800-1234')
                            ->visible(fn (callable $get) => (bool) $get('homepage_featured')),
                        TextInput::make('homepage_contact_wechat')
                            ->label('微信')
                            ->maxLength(128)
                            ->placeholder('例如：BeeGuardService')
                            ->visible(fn (callable $get) => (bool) $get('homepage_featured')),
                        TextInput::make('homepage_contact_website')
                            ->label('官网链接')
                            ->url()
                            ->maxLength(255)
                            ->placeholder('https://example.com')
                            ->visible(fn (callable $get) => (bool) $get('homepage_featured')),
                    ])->columnSpanFull(),
                    Repeater::make('homepageImages')
                        ->label('首页展示图片')
                        ->relationship('homepageImages')
                        ->orderable('position')
                        ->visible(fn (callable $get) => (bool) $get('homepage_featured'))
                        ->minItems(0)
                        ->addActionLabel('添加图片')
                        ->schema([
                            FileUpload::make('path')
                                ->label('图片')
                                ->image()
                                ->disk('public')
                                ->directory('product-homepage')
                                ->maxSize(3072)
                                ->required()
                                ->helperText('支持 JPG/PNG，最大 3MB。'),
                        ])
                        ->columnSpanFull()
                        ->collapsed(),
                ])
                ->columns(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable()->toggleable(),
                TextColumn::make('enterprise.name')->label('所属企业')->searchable()->sortable(),
                TextColumn::make('name')->label('产品名称')->searchable()->sortable(),
                TextColumn::make('homepage_featured')
                    ->label('首页推荐')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? '是' : '否')
                    ->color(fn ($state) => $state ? 'success' : 'gray')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'active' => '上架',
                        'inactive' => '下架',
                        default => $state,
                    })
                    ->sortable(),
                TextColumn::make('created_at')->label('创建时间')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('enterprise_id')
                    ->label('所属企业')
                    ->relationship('enterprise', 'name'),
                Tables\Filters\SelectFilter::make('status')
                    ->label('状态')
                    ->options([
                        'active' => '上架',
                        'inactive' => '下架',
                    ]),
                Tables\Filters\SelectFilter::make('homepage_featured')
                    ->label('首页推荐')
                    ->options([
                        1 => '是',
                        0 => '否',
                    ])->query(function ($query, $data) {
                        if ($data['value'] === null) {
                            return;
                        }

                        $query->where('homepage_featured', (bool) $data['value']);
                    }),
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
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Admin\Resources\ProductResource\RelationManagers\DiseasesRelationManager::class,
        ];
    }
}
