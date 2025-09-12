<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Validation\Rule;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Users';

    protected static \UnitEnum|string|null $navigationGroup = 'System';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Base Info')
                    ->schema([
                        TextInput::make('name')
                            ->label('Display Name')
                            ->maxLength(191),
                        TextInput::make('username')
                            ->label('Username')
                            ->maxLength(191)
                            ->nullable()
                            ->unique(ignoreRecord: true),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(191)
                            ->nullable()
                            ->unique(ignoreRecord: true),
                        Select::make('role')
                            ->label('Role')
                            ->options([
                                'super_admin' => 'Super Admin',
                                'enterprise_admin' => 'Enterprise Admin',
                                'farmer' => 'Farmer',
                            ])
                            ->required()
                            ->native(false),
                    ])->columns(2),

                Section::make('Credentials')
                    ->schema([
                        TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->revealable()
                            ->rule(function (callable $get) {
                                return Rule::requiredIf(fn () => request()->routeIs('filament.admin.resources.users.create'));
                            })
                            ->confirmed()
                            ->dehydrated(fn ($state) => filled($state))
                            ->dehydrateStateUsing(fn ($state) => $state)
                            ->maxLength(191),
                        TextInput::make('password_confirmation')
                            ->label('Confirm Password')
                            ->password()
                            ->revealable()
                            ->dehydrated(false),
                    ])->columns(2),

                Section::make('WeChat (Optional)')
                    ->schema([
                        TextInput::make('openid')
                            ->label('OpenID')
                            ->maxLength(64)
                            ->nullable()
                            ->unique(ignoreRecord: true),
                        TextInput::make('nickname')
                            ->label('Nickname')
                            ->maxLength(191)
                            ->nullable(),
                        TextInput::make('avatar')
                            ->label('Avatar URL')
                            ->maxLength(512)
                            ->nullable(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable()->toggleable(),
                TextColumn::make('display_name')->label('Name'),
                TextColumn::make('username')->searchable()->toggleable(),
                TextColumn::make('email')->searchable()->toggleable(),
                TextColumn::make('role')->badge()->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->options([
                        'super_admin' => 'Super Admin',
                        'enterprise_admin' => 'Enterprise Admin',
                        'farmer' => 'Farmer',
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
