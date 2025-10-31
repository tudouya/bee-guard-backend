<?php

namespace App\Filament\Admin\Resources\CommunityPostReplies\Schemas;

use App\Models\CommunityPostReply;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Illuminate\Support\HtmlString;

class CommunityPostReplyInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('回复管理')
                    ->icon('heroicon-o-clipboard-document')
                    ->headerActions([
                        Action::make('approveReply')
                            ->label('通过')
                            ->icon('heroicon-o-check-circle')
                            ->color('success')
                            ->visible(fn (CommunityPostReply $record) => $record->status !== 'approved')
                            ->requiresConfirmation()
                            ->action(function (Action $action, CommunityPostReply $record): void {
                                $user = auth()->user();
                                if ($user) {
                                    $record->approve($user);
                                }

                                $livewire = $action->getLivewire();
                                if (method_exists($livewire, 'refreshRecord')) {
                                    $livewire->refreshRecord();
                                }

                                Notification::make()
                                    ->title('回复已通过审核')
                                    ->success()
                                    ->send();
                            }),
                        Action::make('rejectReply')
                            ->label('驳回')
                            ->icon('heroicon-o-x-circle')
                            ->color('danger')
                            ->visible(fn (CommunityPostReply $record) => $record->status !== 'rejected')
                            ->form([
                                Textarea::make('reason')
                                    ->label('驳回原因')
                                    ->required()
                                    ->maxLength(255),
                            ])
                            ->action(function (Action $action, CommunityPostReply $record, array $data): void {
                                $user = auth()->user();
                                if ($user) {
                                    $record->reject($user, $data['reason']);
                                }

                                $livewire = $action->getLivewire();
                                if (method_exists($livewire, 'refreshRecord')) {
                                    $livewire->refreshRecord();
                                }

                                Notification::make()
                                    ->title('回复已驳回')
                                    ->danger()
                                    ->send();
                            }),
                    ])
                    ->columnSpanFull(),

                Section::make('基础信息')
                    ->icon('heroicon-o-square-3-stack-3d')
                    ->columns(4)
                    ->schema([
                        Text::make('所属帖子')->color('gray')->weight('medium')->columnSpan(1),
                        Text::make(fn (CommunityPostReply $record) => $record->post?->title ?: '—')
                            ->columnSpan(3)
                            ->weight('semibold')
                            ->size(TextSize::Large),

                        Text::make('状态')->color('gray')->weight('medium')->columnSpan(1),
                        Text::make(fn (CommunityPostReply $record) => match ($record->status) {
                            'approved' => '已通过',
                            'pending' => '待审核',
                            'rejected' => '已驳回',
                            default => (string) $record->status,
                        })
                            ->badge()
                            ->color(fn (CommunityPostReply $record) => match ($record->status) {
                                'approved' => 'success',
                                'pending' => 'warning',
                                'rejected' => 'danger',
                                default => 'gray',
                            })
                            ->columnSpan(1),

                        Text::make('回复类型')->color('gray')->weight('medium')->columnSpan(1),
                        Text::make(fn (CommunityPostReply $record) => $record->reply_type ?: '—')->columnSpan(1),

                        Text::make('作者')->color('gray')->weight('medium')->columnSpan(1),
                        Text::make(fn (CommunityPostReply $record) => $record->author?->display_name ?: '—')->columnSpan(1),


                        Text::make('发布时间')->color('gray')->weight('medium')->columnSpan(1),
                        Text::make(fn (CommunityPostReply $record) => optional($record->published_at)?->format('Y-m-d') ?: '—')->columnSpan(1),

                        Text::make('创建时间')->color('gray')->weight('medium')->columnSpan(1),
                        Text::make(fn (CommunityPostReply $record) => optional($record->created_at)?->format('Y-m-d') ?: '—')->columnSpan(1),

                        Text::make('审核人')->color('gray')->weight('medium')->columnSpan(1),
                        Text::make(fn (CommunityPostReply $record) => $record->reviewer?->display_name ?: '—')->columnSpan(1),

                        Text::make('审核时间')->color('gray')->weight('medium')->columnSpan(1),
                        Text::make(fn (CommunityPostReply $record) => optional($record->reviewed_at)?->format('Y-m-d') ?: '—')->columnSpan(1),
                    ]),

                Section::make('回复内容')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->schema([
                        Text::make(fn (CommunityPostReply $record) => self::formatContent($record->content))
                            ->columnSpanFull()
                            ->extraAttributes(['class' => 'text-gray-800 leading-relaxed whitespace-pre-line']),
                    ]),

                Section::make('驳回原因')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->visible(fn (CommunityPostReply $record) => filled($record->reject_reason))
                    ->schema([
                        Text::make(fn (CommunityPostReply $record) => $record->reject_reason ?: '—')
                            ->columnSpanFull()
                            ->color('danger')
                            ->extraAttributes(['class' => 'text-sm leading-relaxed']),
                    ]),
            ]);
    }

    private static function formatContent(?string $content): HtmlString
    {
        $trimmed = trim((string) $content);

        if ($trimmed === '') {
            return new HtmlString('<span class="text-gray-400">暂无内容</span>');
        }

        $escaped = e($trimmed);

        return new HtmlString(nl2br($escaped));
    }
}
