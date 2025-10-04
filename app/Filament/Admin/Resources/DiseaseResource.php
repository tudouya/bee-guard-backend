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
                    TextInput::make('name')
                        ->label('病种名称')
                        ->required()
                        ->maxLength(191),
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
                TextColumn::make('updated_at')->dateTime()->label('更新时间')->sortable(),
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
