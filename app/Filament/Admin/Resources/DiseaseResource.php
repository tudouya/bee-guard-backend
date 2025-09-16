<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\DiseaseResource\Pages;
use App\Models\Disease;
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

    protected static ?string $navigationLabel = 'Diseases';

    protected static \UnitEnum|string|null $navigationGroup = 'Dictionary';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Basic')
                ->schema([
                    TextInput::make('code')
                        ->label('Code')
                        ->required()
                        ->maxLength(64)
                        ->unique(ignoreRecord: true),
                    TextInput::make('name')
                        ->label('Name')
                        ->required()
                        ->maxLength(191),
                    TextInput::make('brief')
                        ->label('Brief')
                        ->maxLength(191),
                    Select::make('status')
                        ->label('Status')
                        ->options([
                            'active' => 'Active',
                            'hidden' => 'Hidden',
                        ])->default('active')->required(),
                    TextInput::make('sort')
                        ->label('Sort')
                        ->numeric()
                        ->default(0),
                ])->columns(2),
            Section::make('Details')
                ->schema([
                    Textarea::make('description')
                        ->label('Description')
                        ->rows(4),
                    Textarea::make('symptom')
                        ->label('Symptom')
                        ->rows(3),
                    Textarea::make('transmit')
                        ->label('Transmit')
                        ->rows(3),
                    Textarea::make('prevention')
                        ->label('Prevention')
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
                TextColumn::make('id')->sortable()->toggleable(),
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('published_articles_count')->label('Published Articles')->sortable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('sort')->sortable(),
                TextColumn::make('updated_at')->dateTime()->label('Updated')->sortable(),
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
