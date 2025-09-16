<?php

namespace App\Filament\Admin\Resources\CommunityPosts\Tables;

use App\Models\CommunityPost;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class CommunityPostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('title')->label('标题')->limit(30)->searchable(),
                BadgeColumn::make('type')->label('类型')->formatStateUsing(fn ($state) => $state === 'question' ? '蜂农提问' : '经验分享')->colors([
                    'primary',
                ])->sortable(),
                BadgeColumn::make('status')->label('状态')->colors([
                    'success' => 'approved',
                    'warning' => 'pending',
                    'danger' => 'rejected',
                ])->icons([
                    'heroicon-o-check-circle' => 'approved',
                    'heroicon-o-clock' => 'pending',
                    'heroicon-o-x-circle' => 'rejected',
                ])->sortable(),
                TextColumn::make('author.display_name')->label('作者')->searchable()->sortable(),
                TextColumn::make('category')->label('分类')->toggleable()->placeholder('—'),
                TextColumn::make('disease.name')->label('病种')->toggleable()->placeholder('—'),
                TextColumn::make('likes')->label('点赞')->numeric()->sortable(),
                TextColumn::make('replies_count')->label('回复数')->numeric()->sortable(),
                TextColumn::make('views')->label('浏览')->numeric()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('published_at')->label('发布时间')->dateTime()->sortable(),
                TextColumn::make('created_at')->label('创建时间')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')->options([
                    'question' => '蜂农提问',
                    'experience' => '经验分享',
                ]),
                SelectFilter::make('status')->options([
                    'pending' => '待审核',
                    'approved' => '已通过',
                    'rejected' => '已驳回',
                ]),
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('view')
                    ->modalHeading('帖子详情')
                    ->modalWidth('5xl')
                    ->form([
                        Placeholder::make('title')->label('标题')->content(fn (CommunityPost $record) => $record->title),
                        Placeholder::make('type')->label('类型')->content(fn (CommunityPost $record) => $record->type === 'question' ? '蜂农提问' : '经验分享'),
                        Placeholder::make('status')->label('状态')->content(fn (CommunityPost $record) => $record->status),
                        Placeholder::make('category')->label('分类')->content(fn (CommunityPost $record) => $record->category ?: '—'),
                        Placeholder::make('disease')->label('关联病种')->content(fn (CommunityPost $record) => $record->disease?->name ?: '—'),
                        Placeholder::make('author')->label('作者')->content(fn (CommunityPost $record) => $record->author?->display_name ?: '—'),
                        Placeholder::make('likes')->label('点赞')->content(fn (CommunityPost $record) => (string) $record->likes),
                        Placeholder::make('views')->label('浏览')->content(fn (CommunityPost $record) => (string) $record->views),
                        Placeholder::make('replies')->label('回复数')->content(fn (CommunityPost $record) => (string) $record->replies_count),
                        Placeholder::make('published_at')->label('发布时间')->content(fn (CommunityPost $record) => optional($record->published_at)?->format('Y-m-d H:i') ?: '—'),
                        Placeholder::make('reviewer')->label('审核人')->content(fn (CommunityPost $record) => $record->reviewer?->display_name ?: '—'),
                        Placeholder::make('reviewed_at')->label('审核时间')->content(fn (CommunityPost $record) => optional($record->reviewed_at)?->format('Y-m-d H:i') ?: '—'),
                        Placeholder::make('content')->label('正文')->content(fn (CommunityPost $record) => $record->content ?: '—'),
                        Placeholder::make('images')->label('图片ID')->content(fn (CommunityPost $record) => empty($record->images) ? '—' : implode(', ', array_map('strval', $record->images))),
                        Placeholder::make('reject_reason')->label('驳回原因')->content(fn (CommunityPost $record) => $record->reject_reason ?: '—'),
                    ]),
                Action::make('approve')
                    ->label('通过')
                    ->color('success')
                    ->icon('heroicon-o-check')
                    ->requiresConfirmation()
                    ->visible(fn (CommunityPost $record) => $record->status !== 'approved')
                    ->action(function (CommunityPost $record): void {
                        $user = auth()->user();
                        if ($user) {
                            $record->approve($user);
                        }
                    }),
                Action::make('reject')
                    ->label('驳回')
                    ->color('danger')
                    ->icon('heroicon-o-x-mark')
                    ->requiresConfirmation()
                    ->visible(fn (CommunityPost $record) => $record->status !== 'rejected')
                    ->form([
                        Textarea::make('reason')
                            ->label('驳回原因')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->action(function (CommunityPost $record, array $data): void {
                        $user = auth()->user();
                        if ($user) {
                            $record->reject($user, $data['reason']);
                        }
                    }),
                ActionGroup::make([
                    DeleteAction::make()
                        ->visible(fn (CommunityPost $record) => !$record->trashed())
                        ->modalHeading('删除帖子')
                        ->modalDescription('删除后可从“已删除”筛选中恢复。'),
                    RestoreAction::make()
                        ->visible(fn (CommunityPost $record) => $record->trashed()),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
