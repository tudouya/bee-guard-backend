<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\KnowledgeArticleResource\Pages;
use App\Models\KnowledgeArticle;
use App\Models\Disease;
use App\Support\AdminNavigation;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class KnowledgeArticleResource extends Resource
{
    protected static ?string $model = KnowledgeArticle::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = '知识文章';

    protected static \UnitEnum|string|null $navigationGroup = AdminNavigation::GROUP_KNOWLEDGE;

    protected static ?int $navigationSort = AdminNavigation::ORDER_KNOWLEDGE_ARTICLES;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->schema([
                Grid::make(['default' => 1, 'xl' => 12])->schema([
                    Section::make('基本信息')
                        ->schema([
                        Select::make('disease_id')
                            ->label('关联病种')
                            ->options(fn () => Disease::query()->where('status', 'active')->orderBy('sort')->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('title')
                            ->label('文章标题')
                            ->required()
                            ->maxLength(191),
                        TextInput::make('brief')
                            ->label('摘要')
                            ->maxLength(300),
                        TextInput::make('sort')
                            ->label('排序值')
                            ->numeric()
                            ->default(0)
                            ->required()
                            ->minValue(0),
                        Toggle::make('is_homepage_featured')
                            ->label('推荐到首页')
                            ->default(false),
                    ])
                    ->columns(1)
                    ->columnSpan(['default' => 1, 'xl' => 4]),

                Section::make('正文内容')
                    ->schema([
                        RichEditor::make('body_html')
                            ->label('正文')
                            ->required()
                            ->columnSpanFull()
                            ->extraAttributes(['style' => 'min-height: 400px;'])
                            // 富文本图片附件持久化配置（公开可访问，便于后台/详情页/小程序展示）
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory(fn () => 'knowledge/' . date('Y/m'))
                            ->fileAttachmentsVisibility('public')
                            ->getFileAttachmentUrlUsing(fn ($file) => Storage::disk('public')->url($file)),
                    ])
                    ->columns(1)
                    ->columnSpan(['default' => 1, 'xl' => 8]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(fn () => KnowledgeArticle::query()->with('disease'))
            ->columns([
                TextColumn::make('id')->label('ID')->sortable()->toggleable(),
                TextColumn::make('title')->label('文章标题')->searchable()->sortable()->wrap(),
                TextColumn::make('disease.name')->label('关联病种')->sortable()->searchable(),
                TextColumn::make('sort')->label('排序值')->sortable(),
                IconColumn::make('is_homepage_featured')
                    ->label('首页推荐')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('published_at')->dateTime('Y-m-d')->label('发布时间')->sortable(),
                TextColumn::make('views')->label('浏览量')->sortable(),
                TextColumn::make('updated_at')->dateTime()->label('更新时间')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('disease_id')->label('关联病种')
                    ->options(fn () => Disease::query()->orderBy('sort')->pluck('name', 'id')->all()),
                Tables\Filters\Filter::make('published')
                    ->label('仅看已发布')
                    ->query(fn (Builder $q) => $q->whereNotNull('published_at')),
            ])
            ->actions([
                \Filament\Actions\Action::make('publish')
                    ->label('发布')
                    ->visible(fn (KnowledgeArticle $record) => $record->published_at === null)
                    ->requiresConfirmation()
                    ->action(function (KnowledgeArticle $record) {
                        $record->published_at = now();
                        $record->save();
                    }),
                \Filament\Actions\Action::make('unpublish')
                    ->label('取消发布')
                    ->visible(fn (KnowledgeArticle $record) => $record->published_at !== null)
                    ->requiresConfirmation()
                    ->action(function (KnowledgeArticle $record) {
                        $record->published_at = null;
                        $record->save();
                    }),
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
            'index' => Pages\ListKnowledgeArticles::route('/'),
            'create' => Pages\CreateKnowledgeArticle::route('/create'),
            'edit' => Pages\EditKnowledgeArticle::route('/{record}/edit'),
        ];
    }
}
