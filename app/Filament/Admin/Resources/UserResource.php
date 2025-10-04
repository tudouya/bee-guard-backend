<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\UserResource\Pages;
use App\Models\User;
use App\Support\AdminNavigation;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Actions\Action;
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

    protected static ?string $navigationLabel = '用户管理';

    protected static \UnitEnum|string|null $navigationGroup = AdminNavigation::GROUP_SYSTEM;

    protected static ?int $navigationSort = AdminNavigation::ORDER_USERS;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('基础信息')
                    ->schema([
                        TextInput::make('name')
                            ->label('姓名')
                            ->maxLength(191),
                        TextInput::make('username')
                            ->label('用户名')
                            ->maxLength(191)
                            ->nullable()
                            ->unique(ignoreRecord: true),
                        TextInput::make('email')
                            ->label('邮箱')
                            ->email()
                            ->maxLength(191)
                            ->nullable()
                            ->unique(ignoreRecord: true),
                        Select::make('role')
                            ->label('角色')
                            ->options([
                                'super_admin' => '超管',
                                'enterprise_admin' => '企业管理员',
                                'farmer' => '蜂农',
                            ])
                            ->required()
                            ->native(false),
                    ])->columns(2),

                Section::make('登录凭证')
                    ->schema([
                        TextInput::make('password')
                            ->label('密码')
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
                            ->label('确认密码')
                            ->password()
                            ->revealable()
                            ->dehydrated(false),
                    ])->columns(2),

                Section::make('微信信息（可选）')
                    ->schema([
                        TextInput::make('openid')
                            ->label('OpenID')
                            ->maxLength(64)
                            ->nullable()
                            ->unique(ignoreRecord: true),
                       TextInput::make('nickname')
                            ->label('微信昵称')
                            ->suffixActions([
                                Action::make('nickname-hint')
                                    ->icon('heroicon-m-question-mark-circle')
                                    ->tooltip('仅小程序端展示；Name 为正式名称')
                                    ->disabled(),
                            ])
                            ->maxLength(191)
                            ->nullable(),
                       TextInput::make('avatar')
                            ->label('头像链接')
                            ->maxLength(512)
                            ->nullable(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable()->toggleable(),
                TextColumn::make('display_name')->label('姓名'),
                TextColumn::make('username')->label('用户名')->searchable()->toggleable(),
                TextColumn::make('email')->label('邮箱')->searchable()->toggleable(),
                TextColumn::make('role')
                    ->label('角色')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'super_admin' => '超管',
                        'enterprise_admin' => '企业管理员',
                        'farmer' => '蜂农',
                        default => $state,
                    })
                    ->sortable(),
                TextColumn::make('created_at')->label('创建时间')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->options([
                        'super_admin' => '超管',
                        'enterprise_admin' => '企业管理员',
                        'farmer' => '蜂农',
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
