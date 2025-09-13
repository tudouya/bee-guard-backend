<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ProductResource\Pages;
use App\Models\Product;
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

    protected static ?string $navigationLabel = 'Products';

    protected static \UnitEnum|string|null $navigationGroup = 'Business';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Basic')
                ->schema([
                    Select::make('enterprise_id')
                        ->label('Enterprise')
                        ->relationship('enterprise', 'name')
                        ->required()
                        ->searchable()
                        ->preload(),
                    TextInput::make('name')
                        ->label('Name')
                        ->required()
                        ->maxLength(191),
                    TextInput::make('url')
                        ->label('URL')
                        ->maxLength(512)
                        ->url(),
                    Select::make('status')
                        ->label('Status')
                        ->options([
                            'active' => 'Active',
                            'inactive' => 'Inactive',
                        ])
                        ->required()
                        ->native(false),
                ])->columns(2),

            Section::make('Content')
                ->schema([
                    Textarea::make('brief')->label('Brief')->rows(4),
                    Textarea::make('media')
                        ->label('Media (JSON)')
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
                TextColumn::make('id')->sortable()->toggleable(),
                TextColumn::make('enterprise.name')->label('Enterprise')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('enterprise_id')
                    ->label('Enterprise')
                    ->relationship('enterprise', 'name'),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
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
