<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\EnterpriseResource\Pages;
use App\Models\Enterprise;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EnterpriseResource extends Resource
{
    protected static ?string $model = Enterprise::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Enterprises';

    protected static \UnitEnum|string|null $navigationGroup = 'System';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Base Info')
                ->schema([
                    TextInput::make('name')
                        ->label('Name')
                        ->required()
                        ->maxLength(191),
                    Select::make('owner_user_id')
                        ->label('Owner User')
                        // 使用实际存在的列进行 titleAttribute，避免 SQL 报错
                        ->relationship('owner', 'name')
                        // 仍然显示友好的名称（基于访问器）
                        ->getOptionLabelFromRecordUsing(fn ($record) => (string) ($record->display_name ?? $record->name))
                        ->searchable()
                        ->preload()
                        ->nullable(),
                ])->columns(2),

            Section::make('Contact')
                ->schema([
                    TextInput::make('contact_name')
                        ->label('Contact Name')
                        ->maxLength(191),
                    TextInput::make('contact_phone')
                        ->label('Contact Phone')
                        ->tel()
                        ->maxLength(32),
                ])->columns(2),

            Section::make('Settings')
                ->schema([
                    Select::make('status')
                        ->label('Status')
                        ->options([
                            'active' => 'Active',
                            'inactive' => 'Inactive',
                        ])
                        ->required()
                        ->native(false),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable()->toggleable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('owner.display_name')->label('Owner')->toggleable(),
                TextColumn::make('contact_phone')->label('Phone')->toggleable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
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
            'index' => Pages\ListEnterprises::route('/'),
            'create' => Pages\CreateEnterprise::route('/create'),
            'edit' => Pages\EditEnterprise::route('/{record}/edit'),
        ];
    }
}
