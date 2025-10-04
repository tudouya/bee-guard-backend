<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\DetectionCodeResource\Pages;
use App\Models\DetectionCode;
use App\Support\AdminNavigation;
use Filament\Forms\Components\DatePicker;
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

    protected static ?string $navigationLabel = '检测号池';

    protected static \UnitEnum|string|null $navigationGroup = AdminNavigation::GROUP_DETECTION_OPERATIONS;

    protected static ?int $navigationSort = AdminNavigation::ORDER_DETECTION_CODES;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('基础信息')->schema([
                // 先选择来源：左侧 Source Type（半行），右侧 Enterprise（半行，按需显示）
                Grid::make(2)->schema([
                    Select::make('source_type')
                        ->label('来源类型')
                        ->options([
                            'gift' => '赠送（企业）',
                            'self_paid' => '自费',
                        ])
                        ->required()
                        ->native(false)
                        ->live() // 切换时立即刷新表单，使 Enterprise 显示/隐藏
                        ->afterStateUpdated(function (string $state, Set $set, callable $get) {
                            if ($state === 'gift') {
                                $enterpriseId = (int) ($get('enterprise_id') ?? 0);
                                $enterprisePrefix = null;
                                if ($enterpriseId > 0) {
                                    $enterprise = \App\Models\Enterprise::query()->find($enterpriseId);
                                    $enterprisePrefix = $enterprise?->code_prefix ?: null;
                                }
                                $prefix = $enterprisePrefix ?: DetectionCode::DEFAULT_PREFIX_GIFT;
                            } else {
                                $prefix = DetectionCode::DEFAULT_PREFIX_SELF;
                            }
                            $set('prefix', $prefix);
                            $set('code', self::previewRandomCode());
                        })
                        ->columnSpan(1),
                    Select::make('enterprise_id')
                        ->label('所属企业')
                        ->relationship('enterprise', 'name')
                        ->searchable()
                        ->preload()
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set, callable $get) {
                            // When enterprise changes and source is gift, prefer enterprise code_prefix
                            if ($get('source_type') !== 'gift') {
                                return;
                            }
                            $enterprisePrefix = null;
                            $enterpriseId = (int) ($state ?? 0);
                            if ($enterpriseId > 0) {
                                $enterprise = \App\Models\Enterprise::query()->find($enterpriseId);
                                $enterprisePrefix = $enterprise?->code_prefix ?: null;
                            }
                            $prefix = $enterprisePrefix ?: DetectionCode::DEFAULT_PREFIX_GIFT;
                            $set('prefix', $prefix);
                            $set('code', self::previewRandomCode());
                        })
                        ->visible(fn (callable $get) => $get('source_type') === 'gift')
                        ->required(fn (callable $get) => $get('source_type') === 'gift')
                        ->columnSpan(1),
                ]),

                // 再展示生成的完整码组件：左 code、右 prefix
                Grid::make(['default' => 1, 'md' => 2])->schema([
                    TextInput::make('code')
                        ->label('检测号')
                        ->helperText('自动生成的 10 位大写编码（非纯数字）')
                        ->readOnly()
                        ->required()
                        ->maxLength(10),
                    TextInput::make('prefix')
                        ->label('前缀')
                        ->helperText('根据来源自动设置，避免人工误填')
                        ->readOnly()
                        ->required()
                        ->maxLength(16),
                ]),

                // 行3：左侧 Status（半行），右侧留空
                Grid::make(2)->schema([
                    Select::make('status')
                        ->label('状态')
                        ->options([
                            'available' => '可用',
                            'assigned' => '已分配',
                            'used' => '已使用',
                            'expired' => '已过期',
                        ])
                        ->default('available')
                        ->required()
                        ->native(false)
                        ->columnSpan(1),
                ]),
            ]),

            Section::make('分配信息')->schema([
                // Row 1: Assigned User alone for better readability
                Grid::make(['default' => 1])->schema([
                    Select::make('assigned_user_id')
                        ->label('分配用户')
                        ->helperText('按手机号/昵称/用户名/邮箱搜索，至少输入 2 个字符')
                        // 保持关系绑定用于保存；但搜索改为远程检索，禁用预加载
                        ->relationship('assignedUser', 'name')
                        ->searchable()
                        ->getSearchResultsUsing(function (string $search) {
                            $term = trim($search);
                            if (mb_strlen($term) < 2) {
                                return [];
                            }
                            $query = \App\Models\User::query()
                                ->select(['id','phone','nickname','name','username','email'])
                                ->where(function ($q) use ($term) {
                                    $q->where('phone', 'like', $term.'%')
                                      ->orWhere('nickname', 'like', '%'.$term.'%')
                                      ->orWhere('username', 'like', '%'.$term.'%')
                                      ->orWhere('email', 'like', '%'.$term.'%')
                                      ->orWhere('name', 'like', '%'.$term.'%');
                                })
                                ->limit(20)
                                ->get();
                            $out = [];
                            foreach ($query as $u) {
                                $label = (string) ($u->nickname ?: ($u->name ?: ($u->username ?: ($u->email ?: ('用户 #'.$u->id)))));
                                if (filled($u->phone)) {
                                    $label .= ' · '.$u->phone;
                                }
                                $out[$u->id] = $label;
                            }
                            return $out;
                        })
                        ->getOptionLabelUsing(function ($value): ?string {
                            if (empty($value)) {
                                return null;
                            }
                            $u = \App\Models\User::query()->select(['id','phone','nickname','name','username','email'])->find($value);
                            if (! $u) {
                                return '用户 #'.$value;
                            }
                            $label = (string) ($u->nickname ?: ($u->name ?: ($u->username ?: ($u->email ?: ('用户 #'.$u->id)))));
                            return filled($u->phone) ? ($label.' · '.$u->phone) : $label;
                        })
                        ->nullable(),
                ]),
                // Row 2: Timestamps in two columns
                Grid::make(['default' => 1, 'md' => 2])->schema([
                    DatePicker::make('assigned_at')->label('分配时间')->native(false),
                    DatePicker::make('used_at')->label('使用时间')->native(false),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable()->toggleable(),
                TextColumn::make('code')
                    ->label('检测号')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('source_type')
                    ->label('来源类型')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'gift' => '企业赠送',
                        'self_paid' => '自费',
                        default => $state,
                    }),
                TextColumn::make('prefix')
                    ->label('前缀')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'available' => '可用',
                        'assigned' => '已分配',
                        'used' => '已使用',
                        'expired' => '已过期',
                        default => $state,
                    }),
                TextColumn::make('enterprise.name')
                    ->label('所属企业')
                    ->toggleable(),
                TextColumn::make('assignedUser.display_name')
                    ->label('分配用户')
                    ->toggleable(),
                TextColumn::make('assigned_at')
                    ->label('分配时间')
                    ->date()
                    ->sortable(),
                TextColumn::make('used_at')
                    ->label('使用时间')
                    ->date()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('创建时间')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('source_type')
                    ->label('来源类型')
                    ->options([
                        'gift' => '企业赠送',
                        'self_paid' => '自费',
                    ]),
                SelectFilter::make('status')
                    ->label('状态')
                    ->options([
                        'available' => '可用',
                        'assigned' => '已分配',
                        'used' => '已使用',
                        'expired' => '已过期',
                    ]),
                SelectFilter::make('enterprise_id')
                    ->label('所属企业')
                    ->relationship('enterprise', 'name'),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkAction::make('markExpired')
                    ->label('批量标记为已过期')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(fn (array $records) => collect($records)->each->update(['status' => 'expired'])),
                \Filament\Actions\BulkAction::make('markAvailable')
                    ->label('批量标记为可用')
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
