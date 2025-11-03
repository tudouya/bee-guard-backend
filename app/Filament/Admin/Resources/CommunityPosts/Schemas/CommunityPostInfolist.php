<?php

namespace App\Filament\Admin\Resources\CommunityPosts\Schemas;

use App\Models\CommunityPost;
use App\Models\Disease;
use App\Models\Upload;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Image;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class CommunityPostInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('分类与病种')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->schema([
                        Form::make()
                            ->id('community-post-metadata-form')
                            ->statePath('metadata')
                            ->afterStateHydrated(function (Form $form): void {
                                $record = $form->getRecord();

                                if (!$record instanceof CommunityPost) {
                                    return;
                                }

                                $form->state([
                                    'category' => $record->category,
                                    'disease_id' => $record->disease_id,
                                ]);
                            })
                            ->columns(['default' => 1, 'md' => 2])
                            ->schema([
                                Select::make('category')
                                    ->label('分类')
                                    ->native(false)
                                    ->options([
                                        '健康养殖' => '健康养殖',
                                        '疫病防控' => '疫病防控',
                                        '蜜蜂产品' => '蜜蜂产品',
                                        '蜜蜂育种' => '蜜蜂育种',
                                        '蜜蜂授粉' => '蜜蜂授粉',
                                        '市场信息' => '市场信息',
                                    ])
                                    ->searchable()
                                    ->placeholder('未选择')
                                    ->nullable(),
                                Select::make('disease_id')
                                    ->label('关联病种')
                                    ->searchable()
                                    ->native(false)
                                    ->options(fn () => Disease::query()
                                        ->where('status', 'active')
                                        ->orderBy('sort')
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->all())
                                    ->placeholder('未选择')
                                    ->nullable(),
                            ]),
                    ])
                    ->headerActions([
                        Action::make('saveMetadata')
                            ->label('保存信息')
                            ->icon('heroicon-o-document-check')
                            ->color('primary')
                            ->action(function (Action $action, CommunityPost $record): void {
                                $livewire = $action->getLivewire();
                                $metadata = (array) ($livewire->metadata ?? []);

                                $category = trim((string) ($metadata['category'] ?? ''));
                                $diseaseId = $metadata['disease_id'] ?? null;

                                $record->update([
                                    'category' => $category !== '' ? $category : null,
                                    'disease_id' => $diseaseId ?: null,
                                ]);

                                if (method_exists($livewire, 'refreshRecord')) {
                                    $livewire->refreshRecord();
                                }

                                Notification::make()
                                    ->title('分类信息已更新')
                                    ->success()
                                    ->send();
                            }),
                        Action::make('approvePost')
                            ->label('通过')
                            ->icon('heroicon-o-check-circle')
                            ->color('success')
                            ->visible(fn (CommunityPost $record) => $record->status !== 'approved')
                            ->requiresConfirmation()
                            ->action(function (Action $action, CommunityPost $record): void {
                                $user = auth()->user();
                                if ($user) {
                                    $record->approve($user);
                                }

                                $livewire = $action->getLivewire();
                                if (method_exists($livewire, 'refreshRecord')) {
                                    $livewire->refreshRecord();
                                }

                                Notification::make()
                                    ->title('帖子已通过审核')
                                    ->success()
                                    ->send();
                            }),
                        Action::make('rejectPost')
                            ->label('驳回')
                            ->icon('heroicon-o-x-circle')
                            ->color('danger')
                            ->visible(fn (CommunityPost $record) => $record->status !== 'rejected')
                            ->form([
                                Textarea::make('reason')
                                    ->label('驳回原因')
                                    ->required()
                                    ->maxLength(255),
                            ])
                            ->action(function (Action $action, CommunityPost $record, array $data): void {
                                $user = auth()->user();
                                if ($user) {
                                    $record->reject($user, $data['reason']);
                                }

                                $livewire = $action->getLivewire();
                                if (method_exists($livewire, 'refreshRecord')) {
                                    $livewire->refreshRecord();
                                }

                                Notification::make()
                                    ->title('帖子已驳回')
                                    ->danger()
                                    ->send();
                            }),
                    ])
                    ->columnSpanFull(),

                Section::make('帖子概览')
                    ->icon('heroicon-o-document-text')
                    ->columns(4)
                    ->schema([
                        Text::make('标题')->color('gray')->weight('medium')->columnSpan(1),
                        Text::make(fn (CommunityPost $record) => $record->title ?: '—')
                            ->weight('semibold')
                            ->size(TextSize::Large)
                            ->columnSpan(3)
                            ->extraAttributes(['class' => 'text-gray-900 leading-tight']),

                        Text::make('类型')->color('gray')->weight('medium')->columnSpan(1),
                        Text::make(fn (CommunityPost $record) => $record->type === 'question' ? '蜂农提问' : '经验分享')
                            ->badge()
                            ->color(fn (CommunityPost $record) => $record->type === 'question' ? 'warning' : 'primary')
                            ->columnSpan(1),

                        Text::make('状态')->color('gray')->weight('medium')->columnSpan(1),
                        Text::make(fn (CommunityPost $record) => match ($record->status) {
                            'approved' => '已通过',
                            'pending' => '待审核',
                            'rejected' => '已驳回',
                            default => (string) $record->status,
                        })
                            ->badge()
                            ->color(fn (CommunityPost $record) => match ($record->status) {
                                'approved' => 'success',
                                'pending' => 'warning',
                                'rejected' => 'danger',
                                default => 'gray',
                            })
                            ->columnSpan(1),

                        Text::make('分类')->color('gray')->weight('medium')->columnSpan(1),
                        Text::make(fn (CommunityPost $record) => $record->category ?: '—')->columnSpan(3),

                        Text::make('关联病种')->color('gray')->weight('medium')->columnSpan(1),
                        Text::make(fn (CommunityPost $record) => $record->disease?->name ?: '—')->columnSpan(3),

                        Text::make('作者')->color('gray')->weight('medium')->columnSpan(1),
                        Text::make(fn (CommunityPost $record) => $record->author?->display_name ?: '—')
                            ->columnSpan(3),

                        Text::make('创建时间')->color('gray')->weight('medium')->columnSpan(1),
                        Text::make(fn (CommunityPost $record) => optional($record->created_at)?->format('Y-m-d') ?: '—')->columnSpan(1),

                        Text::make('发布时间')->color('gray')->weight('medium')->columnSpan(1),
                        Text::make(fn (CommunityPost $record) => optional($record->published_at)?->format('Y-m-d') ?: '—')->columnSpan(1),

                        Text::make('最后更新')->color('gray')->weight('medium')->columnSpan(1),
                        Text::make(fn (CommunityPost $record) => optional($record->updated_at)?->format('Y-m-d') ?: '—')->columnSpan(3),
                    ]),

                Section::make('互动数据')
                    ->icon('heroicon-o-chart-bar')
                    ->columns(4)
                    ->schema([
                        Text::make('浏览')->color('gray')->weight('medium')->columnSpan(1),
                        Text::make(fn (CommunityPost $record) => (string) $record->views)->columnSpan(1),

                        Text::make('点赞')->color('gray')->weight('medium')->columnSpan(1),
                        Text::make(fn (CommunityPost $record) => (string) $record->likes)->columnSpan(1),

                        Text::make('回复数')->color('gray')->weight('medium')->columnSpan(1),
                        Text::make(fn (CommunityPost $record) => (string) $record->replies_count)->columnSpan(1),
                    ]),

                Section::make('审核信息')
                    ->icon('heroicon-o-shield-check')
                    ->columns(4)
                    ->schema([
                        Text::make('审核人')->color('gray')->weight('medium')->columnSpan(1),
                        Text::make(fn (CommunityPost $record) => $record->reviewer?->display_name ?: '—')->columnSpan(3),

                        Text::make('审核时间')->color('gray')->weight('medium')->columnSpan(1),
                        Text::make(fn (CommunityPost $record) => optional($record->reviewed_at)?->format('Y-m-d') ?: '—')->columnSpan(3),

                        Text::make('驳回原因')->color('gray')->weight('medium')
                            ->columnSpan(1)
                            ->visible(fn (CommunityPost $record) => filled($record->reject_reason)),
                        Text::make(fn (CommunityPost $record) => $record->reject_reason ?: '—')
                            ->color('danger')
                            ->columnSpan(3)
                            ->visible(fn (CommunityPost $record) => filled($record->reject_reason)),
                    ]),

                Section::make('正文内容')
                    ->icon('heroicon-o-newspaper')
                    ->schema(fn (CommunityPost $record) => self::buildContentSection($record)),
            ]);
    }

    private static function formatContent(?string $content): HtmlString
    {
        if (blank($content)) {
            return new HtmlString('<span class="text-gray-400">暂无正文</span>');
        }

        $paragraphs = collect(preg_split('/\r\n|\r|\n/', trim((string) $content)))
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->map(fn (string $line) => '<p class="leading-relaxed text-gray-800 break-words break-all whitespace-pre-wrap" style="word-break: break-all; overflow-wrap: anywhere;">' . e($line) . '</p>')
            ->implode('');

        if ($paragraphs === '') {
            return new HtmlString('<span class="text-gray-400">暂无正文</span>');
        }

        return new HtmlString('<div class="space-y-3 break-words break-all whitespace-pre-wrap max-w-full overflow-hidden" style="word-break: break-all; overflow-wrap: anywhere;">' . $paragraphs . '</div>');
    }

    private static function resolveImageUrls(CommunityPost $record): array
    {
        $ids = collect($record->images ?? [])
            ->map(fn ($value) => (int) $value)
            ->filter(fn (int $value) => $value > 0)
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $uploads = Upload::query()->whereIn('id', $ids)->get()->keyBy('id');

        return $ids->filter(fn (int $id) => $uploads->has($id))
            ->map(function (int $id) use ($uploads) {
                $upload = $uploads->get($id);
                $url = $upload && $upload->path ? Storage::disk($upload->disk)->url($upload->path) : null;

                return $url ? ['id' => $id, 'url' => $url] : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    private static function buildContentSection(CommunityPost $record): array
    {
        $components = [
            Text::make(fn () => self::formatContent($record->content))
                ->columnSpanFull()
                ->extraAttributes([
                    'class' => 'text-gray-800 text-base leading-relaxed break-words break-all whitespace-pre-wrap max-w-full overflow-hidden',
                    'style' => 'word-break: break-all; overflow-wrap: anywhere;',
                ]),
        ];

        $images = self::resolveImageUrls($record);

        if (!empty($images)) {
            $components[] = Grid::make(['default' => 1, 'md' => 2, 'xl' => 3])
                ->schema(collect($images)->map(function (array $image, int $index) {
                    return Image::make($image['url'], '帖子图片 #' . ($index + 1))
                        ->imageHeight('14rem')
                        ->extraAttributes([
                            'class' => 'rounded-xl border border-gray-100 shadow-sm object-cover w-full cursor-zoom-in',
                            'onclick' => "window.open('{$image['url']}', '_blank')",
                        ])
                        ->tooltip('点击查看原图');
                })->all())
                ->columnSpanFull();
        }

        return $components;
    }
}
