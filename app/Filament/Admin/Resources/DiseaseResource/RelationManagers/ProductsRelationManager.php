<?php

namespace App\Filament\Admin\Resources\DiseaseResource\RelationManagers;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'products';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('priority')
                ->label('Priority')
                ->numeric()
                ->default(0),
            TextInput::make('note')
                ->label('Note')
                ->maxLength(191),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('id')->sortable()->toggleable(),
                TextColumn::make('name')->label('Product')->searchable()->sortable(),
                TextColumn::make('enterprise.name')->label('Enterprise')->sortable(),
                TextColumn::make('pivot.priority')->label('Priority')->sortable(),
                TextColumn::make('pivot.note')->label('Note')->wrap(),
                TextColumn::make('updated_at')->date('Y-m-d')->label('Updated')->sortable(),
            ])
            ->headerActions([
                \Filament\Actions\AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->where('status', 'active'))
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DetachAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\DetachBulkAction::make(),
            ]);
    }
}
