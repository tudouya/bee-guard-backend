<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Support\AdminNavigation;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable()->toggleable(),
                TextColumn::make('enterprise.name')->label('所属企业')->searchable()->sortable(),
                TextColumn::make('name')->label('产品名称')->searchable()->sortable(),
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
