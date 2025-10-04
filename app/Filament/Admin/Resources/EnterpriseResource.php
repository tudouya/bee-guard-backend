<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\EnterpriseResource\Pages;
use App\Models\Enterprise;
use App\Services\Naming\EnterprisePrefixSuggester;
use App\Support\AdminNavigation;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Actions\Action;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EnterpriseResource extends Resource
{
    protected static ?string $model = Enterprise::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = '企业管理';

    protected static \UnitEnum|string|null $navigationGroup = AdminNavigation::GROUP_SYSTEM;

    protected static ?int $navigationSort = AdminNavigation::ORDER_ENTERPRISES;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('基础信息')
                ->schema([
                    TextInput::make('name')
                        ->label('企业名称')
                        ->required()
                        ->maxLength(191)
                        ->afterStateUpdated(function (string $state, Set $set, callable $get) {
                            // 当企业名称变化时，如 code_prefix 为空，则自动给出建议
                            $current = (string) ($get('code_prefix') ?? '');
                            if ($current === '') {
                                $suggest = app(EnterprisePrefixSuggester::class)->suggest($state);
                                if ($suggest !== '') {
                                    $set('code_prefix', $suggest);
                                }
                            }
                        }),
                   Select::make('owner_user_id')
                        ->label('主账号')
                        // 使用实际存在的列进行 titleAttribute，避免 SQL 报错
                        ->relationship('owner', 'name')
                        // 仍然显示友好的名称（基于访问器）
                        ->getOptionLabelFromRecordUsing(fn ($record) => (string) ($record->display_name ?? $record->name))
                        ->searchable()
                        ->preload()
                        ->nullable(),
                ])->columns(2),

            Section::make('联系人')
                ->schema([
                    TextInput::make('contact_name')
                        ->label('联系人姓名')
                        ->maxLength(191),
                    TextInput::make('contact_phone')
                        ->label('联系电话')
                        ->tel()
                        ->maxLength(32),
                ])->columns(2),

            Section::make('设置')
                ->schema([
                    SchemaActions::make([
                        Action::make('suggestPrefix')
                            ->label('生成建议前缀')
                            ->icon('heroicon-o-light-bulb')
                            ->action(function (Set $set, callable $get) {
                                $name = (string) ($get('name') ?? '');
                                if ($name !== '') {
                                    $suggest = app(EnterprisePrefixSuggester::class)->suggest($name);
                                    if ($suggest !== '') {
                                        $set('code_prefix', $suggest);
                                    }
                                }
                            }),
                    ])->fullWidth(false),
                    TextInput::make('code_prefix')
                        ->label('检测号前缀（可选）')
                        ->helperText('仅大写字母/数字/连字符，1-16 位。赠送码优先使用企业前缀，留空则使用系统默认（QY）。')
                        ->maxLength(16)
                        ->regex('/^[A-Z0-9-]{1,16}$/')
                        ->nullable()
                        ->dehydrateStateUsing(fn ($state) => $state ? strtoupper($state) : null),
                   Select::make('status')
                        ->label('状态')
                        ->options([
                            'active' => '启用',
                            'inactive' => '停用',
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
                TextColumn::make('id')->label('ID')->sortable()->toggleable(),
                TextColumn::make('name')->label('企业名称')->searchable()->sortable(),
                TextColumn::make('owner.display_name')->label('主账号')->toggleable(),
                TextColumn::make('contact_phone')->label('联系电话')->toggleable(),
                TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'active' => '启用',
                        'inactive' => '停用',
                        default => $state,
                    })
                    ->sortable(),
                TextColumn::make('created_at')->label('创建时间')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => '启用',
                        'inactive' => '停用',
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
