<?php

namespace App\Filament\Admin\Resources\ProductResource\RelationManagers;

use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DiseasesRelationManager extends RelationManager
{
    protected static string $relationship = 'diseases';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('priority')->label('Priority')->numeric()->default(0),
            TextInput::make('note')->label('Note')->maxLength(191),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('id')->sortable()->toggleable(),
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('pivot.priority')->label('Priority')->sortable(),
                TextColumn::make('pivot.note')->label('Note')->wrap(),
                TextColumn::make('updated_at')->date('Y-m-d')->label('Updated')->sortable(),
            ])
            ->headerActions([
                \Filament\Actions\AttachAction::make()
                    ->preloadRecordSelect(),
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
