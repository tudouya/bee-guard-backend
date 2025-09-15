<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\DetectionCodeResource\Pages;
use App\Models\DetectionCode;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DetectionCodeResource extends Resource
{
    protected static ?string $model = DetectionCode::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-qr-code';

    protected static ?string $navigationLabel = 'Detection Codes';

    protected static \UnitEnum|string|null $navigationGroup = 'Business';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Basic Info')->schema([
                // 先选择来源：左侧 Source Type（半行），右侧 Enterprise（半行，按需显示）
                Grid::make(2)->schema([
                    Select::make('source_type')
                        ->label('Source Type')
                        ->options([
                            'gift' => 'Gift (Enterprise)',
                            'self_paid' => 'Self Paid',
                        ])
                        ->required()
                        ->native(false)
                        ->live() // 切换时立即刷新表单，使 Enterprise 显示/隐藏
                        ->afterStateUpdated(function (string $state, Set $set) {
                            $self = strtoupper((string) env('DETCODE_PREFIX_SELF', 'ZF'));
                            $gift = strtoupper((string) env('DETCODE_PREFIX_ENTERPRISE', 'QY'));
                            $prefix = $state === 'gift' ? $gift : $self;
                            $set('prefix', $prefix);
                            $set('code', self::previewRandomCode());
                        })
                        ->columnSpan(1),
                    Select::make('enterprise_id')
                        ->label('Enterprise')
                        ->relationship('enterprise', 'name')
                        ->searchable()
                        ->preload()
                        ->visible(fn (callable $get) => $get('source_type') === 'gift')
                        ->required(fn (callable $get) => $get('source_type') === 'gift')
                        ->columnSpan(1),
                ]),

                // 再展示生成的完整码组件：左 code、右 prefix
                Grid::make(['default' => 1, 'md' => 2])->schema([
                    TextInput::make('code')
                        ->label('Code')
                        ->helperText('自动生成的 10 位大写编码（非纯数字）')
                        ->readOnly()
                        ->required()
                        ->maxLength(10),
                    TextInput::make('prefix')
                        ->label('Prefix')
                        ->helperText('根据来源自动设置，避免人工误填')
                        ->readOnly()
                        ->required()
                        ->maxLength(16),
                ]),

                // 行3：左侧 Status（半行），右侧留空
                Grid::make(2)->schema([
                    Select::make('status')
                        ->label('Status')
                        ->options([
                            'available' => 'Available',
                            'assigned' => 'Assigned',
                            'used' => 'Used',
                            'expired' => 'Expired',
                        ])
                        ->default('available')
                        ->required()
                        ->native(false)
                        ->columnSpan(1),
                ]),
            ]),

            Section::make('Assignment (Optional)')->schema([
                Grid::make(['default' => 1, 'md' => 3])->schema([
                    Select::make('assigned_user_id')
                        ->label('Assigned User')
                        // 使用实际列作为 titleAttribute，避免 SQL 查询不存在的列
                        ->relationship('assignedUser', 'name')
                        ->getOptionLabelFromRecordUsing(fn ($record) => (string) ($record->display_name
                            ?? $record->name
                            ?? $record->username
                            ?? $record->email
                            ?? ''))
                        ->searchable()
                        ->preload()
                        ->nullable(),
                    DateTimePicker::make('assigned_at')->label('Assigned At')->seconds(false),
                    DateTimePicker::make('used_at')->label('Used At')->seconds(false),
                ]),
            ])->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable()->toggleable(),
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('source_type')->badge()->sortable(),
                TextColumn::make('prefix')->sortable()->toggleable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('enterprise.name')->label('Enterprise')->toggleable(),
                TextColumn::make('assignedUser.display_name')->label('Assigned User')->toggleable(),
                TextColumn::make('assigned_at')->dateTime()->sortable(),
                TextColumn::make('used_at')->dateTime()->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('source_type')->options([
                    'gift' => 'Gift (Enterprise)',
                    'self_paid' => 'Self Paid',
                ]),
                SelectFilter::make('status')->options([
                    'available' => 'Available',
                    'assigned' => 'Assigned',
                    'used' => 'Used',
                    'expired' => 'Expired',
                ]),
                SelectFilter::make('enterprise_id')->label('Enterprise')->relationship('enterprise', 'name'),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkAction::make('markExpired')
                    ->label('Mark as Expired')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(fn (array $records) => collect($records)->each->update(['status' => 'expired'])),
                \Filament\Actions\BulkAction::make('markAvailable')
                    ->label('Mark as Available')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->action(fn (array $records) => collect($records)->each->update(['status' => 'available'])),
                \Filament\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDetectionCodes::route('/'),
            'create' => Pages\CreateDetectionCode::route('/create'),
            'edit' => Pages\EditDetectionCode::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Admin\Resources\DetectionCodeResource\RelationManagers\ShippingNotificationsRelationManager::class,
        ];
    }


    // 仅用于前端预览的随机码（最终以服务端生成并校验为准）。
    protected static function previewRandomCode(): string
    {
        $chars = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'; // 去除易混淆字符 IO01
        $len = strlen($chars);
        do {
            $out = '';
            for ($i = 0; $i < 10; $i++) {
                $out .= $chars[random_int(0, $len - 1)];
            }
        } while (! preg_match('/[A-Z]/', $out)); // 至少包含一个字母，避免纯数字

        return $out;
    }
}
