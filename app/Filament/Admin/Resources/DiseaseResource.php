<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\DiseaseResource\Pages;
use App\Models\Disease;
use App\Support\AdminNavigation;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DiseaseResource extends Resource
{
    protected static ?string $model = Disease::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-beaker';

    protected static ?string $navigationLabel = '病种字典';

    protected static \UnitEnum|string|null $navigationGroup = AdminNavigation::GROUP_KNOWLEDGE;

    protected static ?int $navigationSort = AdminNavigation::ORDER_DISEASES;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('基础信息')
                ->schema([
                    TextInput::make('code')
                        ->label('病种代码')
                        ->required()
                        ->maxLength(64)
                        ->unique(ignoreRecord: true),
                    Select::make('category')
                        ->label('分类')
                        ->options([
                            'rna' => 'RNA 病毒',
                            'dna' => 'DNA/细菌/真菌',
                            'pest' => '虫害',
                            'other' => '其他',
                        ])
                        ->native(false)
                        ->nullable()
                        ->helperText('用于分组展示与推荐过滤'),
                    TextInput::make('name')
                        ->label('病种名称')
                        ->required()
                        ->maxLength(191),
                    TextInput::make('map_alias')
                        ->label('地图展示名称')
                        ->maxLength(191)
                        ->helperText('若留空则使用病种名称。'),
                    TextInput::make('map_color')
                        ->label('地图颜色')
                        ->placeholder('#F05A5A')
                        ->maxLength(7)
                        ->regex('/^#?[0-9A-Fa-f]{6}$/')
                        ->helperText('16 进制颜色值，例如 #F05A5A'),
                    TextInput::make('brief')
                        ->label('摘要')
                        ->maxLength(191),
                    Select::make('status')
                        ->label('状态')
                        ->options([
                            'active' => '展示',
                            'hidden' => '隐藏',
                        ])->default('active')->required(),
                    TextInput::make('sort')
                        ->label('排序值')
                        ->numeric()
                        ->default(0),
                    TextInput::make('map_order')
                        ->label('地图排序')
                        ->numeric()
                        ->default(0)
                        ->helperText('用于疫情地图图例排序，值越小越靠前。'),
                ])->columns(2),
            Section::make('详情内容')
                ->schema([
                    Textarea::make('description')
                        ->label('病种描述')
                        ->rows(4),
                    Textarea::make('symptom')
                        ->label('症状表现')
                        ->rows(3),
                    Textarea::make('transmit')
                        ->label('传播途径')
                        ->rows(3),
                    Textarea::make('prevention')
                        ->label('防控建议')
                        ->rows(3),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount([
                'knowledgeArticles as published_articles_count' => function (Builder $q) {
                    $q->whereNotNull('published_at')->where('published_at', '<=', now());
                }
            ]))
            ->columns([
                TextColumn::make('id')->label('ID')->sortable()->toggleable(),
                TextColumn::make('code')->label('病种代码')->searchable()->sortable(),
                TextColumn::make('name')->label('病种名称')->searchable()->sortable(),
                TextColumn::make('category')
                    ->label('分类')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'rna' => 'RNA 病毒',
                        'dna' => 'DNA/细菌/真菌',
                        'pest' => '虫害',
                        'other' => '其他',
                        default => '—',
                    })
                    ->sortable(),
                TextColumn::make('published_articles_count')->label('发布文章数')->sortable(),
                TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'active' => '展示',
                        'hidden' => '隐藏',
                        default => $state,
                    })
                    ->sortable(),
                TextColumn::make('sort')->label('排序值')->sortable(),
                TextColumn::make('map_order')->label('地图排序')->sortable()->toggleable(),
                TextColumn::make('updated_at')->date('Y-m-d')->label('更新时间')->sortable(),
            ])
            ->filters([])
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
            'index' => Pages\ListDiseases::route('/'),
            'create' => Pages\CreateDisease::route('/create'),
            'edit' => Pages\EditDisease::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Admin\Resources\DiseaseResource\RelationManagers\ProductsRelationManager::class,
        ];
    }
}
