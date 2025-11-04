<?php

namespace App\Filament\Admin\Resources;

use App\Enums\RewardIssuanceStatus;
use App\Filament\Admin\Resources\RewardIssuanceResource\Pages;
use App\Models\RewardIssuance;
use App\Services\Community\Rewards\RewardIssuer;
use App\Support\AdminNavigation;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\App;

class RewardIssuanceResource extends Resource
{
    protected static ?string $model = RewardIssuance::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-ticket';
    }

    public static function getNavigationLabel(): string
    {
        return '奖励发放队列';
    }

    public static function getNavigationGroup(): ?string
    {
        return AdminNavigation::GROUP_REWARDS;
    }

    public static function getNavigationSort(): ?int
    {
        return AdminNavigation::ORDER_REWARD_ISSUANCES;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('奖励信息')
                ->schema([
                    Placeholder::make('rule')
                        ->label('所属规则')
                        ->content(fn (RewardIssuance $record) => $record->rewardRule?->name ?? '-'),
                    Placeholder::make('status')
                        ->label('状态')
                        ->content(fn (RewardIssuance $record) => self::statusLabels()[$record->status->value] ?? $record->status->value),
                    Placeholder::make('farmer')
                        ->label('蜂农')
                        ->content(fn (RewardIssuance $record) => $record->farmer?->display_name ?? '-'),
                    Placeholder::make('farmer_phone')
                        ->label('蜂农手机号')
                        ->content(fn (RewardIssuance $record) => $record->farmer?->phone ?? '—'),
                    Placeholder::make('post')
                        ->label('关联内容')
                        ->content(fn (RewardIssuance $record) => $record->post?->title ?? ('帖子 #' . $record->community_post_id)),
                ])->columns([
                    'default' => 1,
                    'md' => 2,
                ]),

            Section::make('购物券信息')
                ->schema([
                    Placeholder::make('coupon_template')
                        ->label('券模板')
                        ->content(fn (RewardIssuance $record) => $record->couponTemplate?->title ?? '—'),
                    Placeholder::make('platform')
                        ->label('平台')
                        ->content(fn (RewardIssuance $record) => $record->store_platform ?? '—'),
                    Placeholder::make('store_name')
                        ->label('店铺名称')
                        ->content(fn (RewardIssuance $record) => $record->store_name ?? '—'),
                    Placeholder::make('store_url')
                        ->label('店铺链接')
                        ->content(fn (RewardIssuance $record) => $record->store_url ?? '—'),
                    Placeholder::make('face_value')
                        ->label('面值')
                        ->content(fn (RewardIssuance $record) => $record->face_value ? number_format((float) $record->face_value, 2) . ' 元' : '—'),
                    Placeholder::make('expires_at')
                        ->label('到期时间')
                        ->content(fn (RewardIssuance $record) => $record->expires_at?->format('Y-m-d') ?? '—'),
                ])->columns([
                    'default' => 1,
                    'md' => 2,
                ]),

            Section::make('使用说明')
                ->schema([
                    Placeholder::make('usage_instructions')
                        ->content(fn (RewardIssuance $record) => $record->usage_instructions ?? '—')
                        ->visible(fn (RewardIssuance $record) => filled($record->usage_instructions)),
                ]),

            Section::make('审核记录')
                ->schema([
                    Placeholder::make('issued_by')
                        ->label('发放人')
                        ->content(fn (RewardIssuance $record) => $record->issuer?->display_name ?? ($record->issued_by ? ('用户 #' . $record->issued_by) : '系统自动')),
                    Placeholder::make('issued_at')
                        ->label('发放时间')
                        ->content(fn (RewardIssuance $record) => $record->issued_at?->format('Y-m-d') ?? '—'),
                    Placeholder::make('audit_log')
                        ->label('审计日志')
                        ->content(fn (RewardIssuance $record) => collect($record->audit_log ?? [])->map(function ($item) {
                            $time = $item['timestamp'] ?? '';
                            $action = $item['action'] ?? 'unknown';
                            return $time . ' - ' . $action;
                        })->implode("\n"))
                        ->columnSpanFull(),
                ])->columns([
                    'default' => 1,
                    'md' => 2,
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('rewardRule.name')
                    ->label('规则')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('farmer.display_name')
                    ->label('蜂农')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('farmer.phone')
                    ->label('蜂农手机号')
                    ->toggleable()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('post.title')
                    ->label('帖子标题')
                    ->toggleable()
                    ->limit(30),
                TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->formatStateUsing(fn (?RewardIssuanceStatus $state) => $state ? self::statusLabels()[$state->value] : $state?->value)
                    ->color(fn (?RewardIssuanceStatus $state) => match ($state) {
                        RewardIssuanceStatus::PendingReview => 'warning',
                        RewardIssuanceStatus::Ready => 'info',
                        RewardIssuanceStatus::Issued => 'success',
                        RewardIssuanceStatus::Used => 'gray',
                        RewardIssuanceStatus::Expired => 'danger',
                        RewardIssuanceStatus::Cancelled => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('store_platform')
                    ->label('平台')
                    ->toggleable(),
                TextColumn::make('issued_at')
                    ->label('发放时间')
                    ->date('Y-m-d')
                    ->sortable(),
                TextColumn::make('expires_at')
                    ->label('到期时间')
                    ->date('Y-m-d')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('状态')
                    ->options(self::statusLabels())
                    ->native(false),
            ])
            ->actions([
                ViewAction::make()->label('查看'),
                Action::make('approve')
                    ->label('通过发放')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (RewardIssuance $record) => $record->status === RewardIssuanceStatus::PendingReview)
                    ->action(function (RewardIssuance $record): void {
                        /** @var RewardIssuer $issuer */
                        $issuer = App::make(RewardIssuer::class);
                        $issuer->approveManual($record, auth()->user());
                    }),
                Action::make('cancel')
                    ->label('驳回')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        Textarea::make('reason')
                            ->label('原因（选填）')
                            ->rows(3),
                    ])
                    ->visible(fn (RewardIssuance $record) => $record->status === RewardIssuanceStatus::PendingReview)
                    ->action(function (RewardIssuance $record, array $data): void {
                        /** @var RewardIssuer $issuer */
                        $issuer = App::make(RewardIssuer::class);
                        $issuer->cancelPending($record, auth()->user(), $data['reason'] ?? '');
                    }),
                DeleteAction::make()
                    ->visible(fn (RewardIssuance $record) => in_array($record->status, [
                        RewardIssuanceStatus::Cancelled,
                        RewardIssuanceStatus::Expired,
                    ], true)),
            ])
            ->bulkActions([])
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['rewardRule', 'farmer', 'post', 'couponTemplate']))
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRewardIssuances::route('/'),
            'view' => Pages\ViewRewardIssuance::route('/{record}'),
        ];
    }

    protected static function statusLabels(): array
    {
        return [
            RewardIssuanceStatus::PendingReview->value => '待审核',
            RewardIssuanceStatus::Ready->value => '已准备',
            RewardIssuanceStatus::Issued->value => '已发放',
            RewardIssuanceStatus::Used->value => '已使用',
            RewardIssuanceStatus::Expired->value => '已过期',
            RewardIssuanceStatus::Cancelled->value => '已取消',
        ];
    }
}
