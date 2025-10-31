<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Support\AdminNavigation;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Filament\Schemas\Components\Grid;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationLabel = '推荐产品';

    protected static \UnitEnum|string|null $navigationGroup = AdminNavigation::GROUP_RECOMMENDATION;

    protected static ?int $navigationSort = AdminNavigation::ORDER_PRODUCTS;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('基本信息')
                ->schema([
                    Select::make('enterprise_id')
                        ->label('所属企业')
                        ->relationship('enterprise', 'name')
                        ->required()
                        ->searchable()
                        ->preload(),
                    TextInput::make('name')
                        ->label('产品名称')
                        ->required()
                        ->maxLength(191),
                    TextInput::make('url')
                        ->label('产品链接')
                        ->maxLength(512)
                        ->url(),
                    Select::make('status')
                        ->label('状态')
                        ->options([
                            'active' => '上架',
                            'inactive' => '下架',
                        ])
                        ->required()
                        ->native(false),
                ])->columns(2),

            Section::make('内容与媒体')
                ->schema([
                    Textarea::make('brief')->label('简介')->rows(4),
                ]),

            Section::make('首页推荐设置')
                ->schema([
                    Toggle::make('homepage_featured')
                        ->label('作为首页推荐展示')
                        ->helperText('开启后将在小程序首页产品推荐入口展示。')
                        ->default(false)
                        ->live(),
                    Grid::make([
                        'default' => 1,
                        'md' => 2,
                    ])->schema([
                        TextInput::make('homepage_sort_order')
                            ->label('首页排序值')
                            ->numeric()
                            ->default(0)
                            ->helperText('数值越小越靠前。')
                            ->visible(fn (callable $get) => (bool) $get('homepage_featured')),
                        TextInput::make('homepage_registration_no')
                            ->label('注册证号')
                            ->maxLength(191)
                            ->placeholder('例如：国械注准 2025-123456')
                            ->visible(fn (callable $get) => (bool) $get('homepage_featured')),
                        Textarea::make('homepage_applicable_scene')
                            ->label('适用场景')
                            ->rows(3)
                            ->helperText('每行填写一个场景，例如：春繁预防 / 夏季高温调理。')
                            ->visible(fn (callable $get) => (bool) $get('homepage_featured')),
                        Textarea::make('homepage_highlights')
                            ->label('产品亮点')
                            ->rows(3)
                            ->helperText('每行填写一条亮点。')
                            ->visible(fn (callable $get) => (bool) $get('homepage_featured')),
                        Textarea::make('homepage_cautions')
                            ->label('注意事项')
                            ->rows(3)
                            ->helperText('每行填写一条注意事项。')
                            ->visible(fn (callable $get) => (bool) $get('homepage_featured')),
                        TextInput::make('homepage_price')
                            ->label('产品价格')
                            ->maxLength(128)
                            ->placeholder('例如：¥199/套')
                            ->visible(fn (callable $get) => (bool) $get('homepage_featured')),
                    ])->columnSpanFull(),
                    Grid::make([
                        'default' => 1,
                        'md' => 2,
                    ])->schema([
                        TextInput::make('homepage_contact_company')
                            ->label('咨询企业名称')
                            ->maxLength(191)
                            ->placeholder('例如：蜂卫士生物科技有限公司')
                            ->visible(fn (callable $get) => (bool) $get('homepage_featured')),
                        TextInput::make('homepage_contact_phone')
                            ->label('联系电话')
                            ->tel()
                            ->maxLength(64)
                            ->placeholder('例如：400-800-1234')
                            ->visible(fn (callable $get) => (bool) $get('homepage_featured')),
                        TextInput::make('homepage_contact_wechat')
                            ->label('微信')
                            ->maxLength(128)
                            ->placeholder('例如：BeeGuardService')
                            ->visible(fn (callable $get) => (bool) $get('homepage_featured')),
                        TextInput::make('homepage_contact_website')
                            ->label('官网链接')
                            ->url()
                            ->maxLength(255)
                            ->placeholder('https://example.com')
                            ->visible(fn (callable $get) => (bool) $get('homepage_featured')),
                    ])->columnSpanFull(),
                    Repeater::make('homepageImages')
                        ->label('首页展示图片')
                        ->relationship('homepageImages')
                        ->orderable('position')
                        ->visible(fn (callable $get) => (bool) $get('homepage_featured'))
                        ->minItems(0)
                        ->addActionLabel('添加图片')
                        ->schema([
                            FileUpload::make('path')
                                ->label('图片')
                                ->image()
                                ->disk('s3')
                                ->directory('product-homepage')
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
                                ->hintAction(fn (FileUpload $component) => Action::make('clearHomepageImage')
                                    ->label('移除图片')
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
                                ->maxSize(3072)
                                ->required()
                                ->helperText('支持 JPG/PNG，最大 3MB。'),
                        ])
                        ->columnSpanFull(),
                ])
                ->columns(1),
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
                TextColumn::make('enterprise.name')->label('所属企业')->searchable()->sortable(),
                TextColumn::make('name')->label('产品名称')->searchable()->sortable(),
                TextColumn::make('homepage_featured')
                    ->label('首页推荐')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? '是' : '否')
                    ->color(fn ($state) => $state ? 'success' : 'gray')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'active' => '上架',
                        'inactive' => '下架',
                        default => $state,
                    })
                    ->sortable(),
                TextColumn::make('created_at')->label('创建时间')->date('Y-m-d')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('enterprise_id')
                    ->label('所属企业')
                    ->relationship('enterprise', 'name'),
                Tables\Filters\SelectFilter::make('status')
                    ->label('状态')
                    ->options([
                        'active' => '上架',
                        'inactive' => '下架',
                    ]),
                Tables\Filters\SelectFilter::make('homepage_featured')
                    ->label('首页推荐')
                    ->options([
                        1 => '是',
                        0 => '否',
                    ])->query(function ($query, $data) {
                        if ($data['value'] === null) {
                            return;
                        }

                        $query->where('homepage_featured', (bool) $data['value']);
                    }),
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
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Admin\Resources\ProductResource\RelationManagers\DiseasesRelationManager::class,
        ];
    }
}
