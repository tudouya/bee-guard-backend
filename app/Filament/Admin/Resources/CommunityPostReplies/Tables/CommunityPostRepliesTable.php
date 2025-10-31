<?php

namespace App\Filament\Admin\Resources\CommunityPostReplies\Tables;

use App\Models\CommunityPostReply;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class CommunityPostRepliesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable()->label('ID'),
                TextColumn::make('post.title')->label('所属帖子')->limit(30)->searchable(),
                TextColumn::make('author.display_name')->label('作者')->searchable(),
                BadgeColumn::make('reply_type')->label('类型')->colors([
                    'primary',
                ]),
                BadgeColumn::make('status')->label('状态')->colors([
                    'success' => 'approved',
                    'warning' => 'pending',
                    'danger' => 'rejected',
                ])->icons([
                    'heroicon-o-check-circle' => 'approved',
                    'heroicon-o-clock' => 'pending',
                    'heroicon-o-x-circle' => 'rejected',
                ]),
                TextColumn::make('content')->label('内容')->limit(40)->searchable(),
                TextColumn::make('published_at')->label('发布时间')->date('Y-m-d')->sortable(),
                TextColumn::make('created_at')->label('创建时间')->date('Y-m-d')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'pending' => '待审核',
                    'approved' => '已通过',
                    'rejected' => '已驳回',
                ]),
                SelectFilter::make('reply_type')->options([
                    'farmer' => '蜂农',
                    'enterprise' => '企业',
                    'platform' => '平台',
                ]),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make()->label('详情'),
                Action::make('approve')
                    ->label('通过')
                    ->color('success')
                    ->icon('heroicon-o-check')
                    ->visible(fn (CommunityPostReply $record) => $record->status !== 'approved')
                    ->requiresConfirmation()
                    ->action(function (CommunityPostReply $record) {
                        $user = auth()->user();
                        if ($user) {
                            $record->approve($user);
                        }
                    }),
                Action::make('reject')
                    ->label('驳回')
                    ->color('danger')
                    ->icon('heroicon-o-x-mark')
                    ->visible(fn (CommunityPostReply $record) => $record->status !== 'rejected')
                    ->requiresConfirmation()
                    ->form([
                        \Filament\Forms\Components\Textarea::make('reason')->label('驳回原因')->required()->maxLength(255),
                    ])
                    ->action(function (CommunityPostReply $record, array $data) {
                        $user = auth()->user();
                        if ($user) {
                            $record->reject($user, $data['reason']);
                        }
                    }),
                ActionGroup::make([
                    DeleteAction::make()->visible(fn (CommunityPostReply $record) => !$record->trashed()),
                    RestoreAction::make()->visible(fn (CommunityPostReply $record) => $record->trashed()),
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
