<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\EnterpriseResource\Pages;
use App\Models\Enterprise;
use App\Services\Naming\EnterprisePrefixSuggester;
use App\Support\AdminNavigation;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\BaseFileUpload;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
                    TextInput::make('contact_wechat')
                        ->label('企业微信')
                        ->maxLength(128)
                        ->placeholder('用于小程序“联系合作”展示，可选'),
                    TextInput::make('contact_link')
                        ->label('官网链接')
                        ->url()
                        ->maxLength(255)
                        ->placeholder('例如：https://example.com'),
                ])->columns(2),

            Section::make('展示信息（小程序）')
                ->schema([
                    Textarea::make('intro')
                        ->label('企业简介')
                        ->rows(4)
                        ->helperText('用于小程序企业列表与详情的简介展示。')
                        ->placeholder('例如：专注蜂群疫病检测与防控，服务覆盖西南 8 省，累计服务蜂场 200+。')
                        ->maxLength(65535),
                    FileUpload::make('logo_url')
                        ->label('企业 Logo 图片')
                        ->image()
                        ->disk('s3')
                        ->directory('enterprise-logos')
                        ->fetchFileInformation(false)
                        ->getUploadedFileUsing(function (BaseFileUpload $component, string $file, string | array | null $storedFileNames): ?array {
                            $file = trim($file);

                            if ($file === '' || Str::startsWith($file, ['livewire-file:', 'livewire-files:'])) {
                                return null;
                            }

                            if (Str::startsWith($file, ['http://', 'https://'])) {
                                $name = ($component->isMultiple() ? ($storedFileNames[$file] ?? null) : $storedFileNames) ?? basename(parse_url($file, PHP_URL_PATH) ?: $file);

                                return [
                                    'name' => $name,
                                    'size' => null,
                                    'type' => null,
                                    'url' => $file,
                                ];
                            }

                            $url = static::resolveStoredFileUrl($component, $file);
                            if ($url === null) {
                                return null;
                            }

                            $name = ($component->isMultiple() ? ($storedFileNames[$file] ?? null) : $storedFileNames) ?? basename($file);

                            return [
                                'name' => $name,
                                'size' => null,
                                'type' => null,
                                'url' => $url,
                            ];
                        })
                        ->hintAction(fn (FileUpload $component) => Action::make('clearLogo')
                            ->label('移除 Logo')
                            ->icon('heroicon-o-trash')
                            ->color('danger')
                            ->requiresConfirmation()
                            ->visible(fn (): bool => filled($component->getState()))
                            ->action(function () use ($component): void {
                                $state = $component->getState();

                                if ($component->isMultiple() || blank($state)) {
                                    $component->state(null);
                                    $component->callAfterStateUpdated();

                                    return;
                                }

                                $file = trim((string) $state);

                                if ($file === '' || Str::startsWith($file, ['http://', 'https://'])) {
                                    $component->state(null);
                                    $component->callAfterStateUpdated();

                                    return;
                                }

                                static::deleteStoredFile($component, $file);

                                $component->state(null);
                                $component->callAfterStateUpdated();
                            }))
                        ->afterStateHydrated(function (FileUpload $component, $state) {
                            if (blank($state)) {
                                $component->state(null);

                                return;
                            }

                            $files = is_array($state) ? $state : [$state];

                            $normalized = collect($files)
                                ->map(function ($value) use ($component) {
                                    $file = trim((string) $value);

                                    if ($file === '' || Str::startsWith($file, ['http://', 'https://'])) {
                                        return $file;
                                    }

                                    return static::resolveStoredFileUrl($component, $file) ? $file : null;
                                })
                                ->filter()
                                ->values()
                                ->all();

                            if (empty($normalized)) {
                                $component->state(null);

                                return;
                            }

                            $component->state($component->isMultiple() ? $normalized : [$normalized[0]]);
                        })
                        ->deleteUploadedFileUsing(function (BaseFileUpload $component, $file): void {
                            if (! is_string($file) || $file === '' || Str::startsWith($file, ['http://', 'https://'])) {
                                return;
                            }

                            static::deleteStoredFile($component, $file);
                        })
                        ->imageEditor()
                        ->maxSize(2048)
                        ->helperText('上传企业 Logo，建议 1:1 或 4:3 比例。文件支持 JPG/PNG，最大 2MB。'),
                    Textarea::make('services')
                        ->label('服务产品列表')
                        ->rows(3)
                        ->helperText('每项服务占一行，系统会按换行拆分为列表展示。')
                        ->placeholder("蜂病检测\n疫病防控培训\n数字化蜂场管理")
                        ->maxLength(512),
                    Textarea::make('certifications')
                        ->label('认证资质')
                        ->rows(3)
                        ->helperText('每行填写一项资质，例如：“蜂业协会认证”。系统按换行拆分。')
                        ->placeholder("蜂业协会认证\nISO9001质量体系认证")
                        ->maxLength(512),
                    Textarea::make('promotions')
                        ->label('优惠活动')
                        ->rows(3)
                        ->helperText('当前仅支持展示一条活动描述，可包含多段文字。留空表示暂无优惠。')
                        ->placeholder('新签客户首单立减100元，详询小程序客服。')
                        ->maxLength(512),
                ])->columns(1),

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

    protected static function resolveStoredFileUrl(BaseFileUpload $component, string $file): ?string
    {
        foreach (static::candidateDisks($component) as $disk) {
            try {
                $url = Storage::disk($disk)->url($file);
            } catch (\Throwable $exception) {
                continue;
            }

            if (blank($url)) {
                continue;
            }

            return $url;
        }

        return null;
    }

    protected static function deleteStoredFile(BaseFileUpload $component, string $file): void
    {
        foreach (static::candidateDisks($component) as $disk) {
            try {
                $filesystem = Storage::disk($disk);

                if ($filesystem->exists($file)) {
                    $filesystem->delete($file);
                }
            } catch (\Throwable $exception) {
                continue;
            }
        }
    }

    /**
     * @return array<int, string>
     */
    protected static function candidateDisks(BaseFileUpload $component): array
    {
        $diskNames = [
            $component->getDiskName(),
            config('filament.default_filesystem_disk'),
            config('filesystems.default'),
            'public',
            's3',
        ];

        return array_values(array_unique(array_filter($diskNames)));
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
                TextColumn::make('created_at')->label('创建时间')->date('Y-m-d')->sortable(),
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
