<?php

namespace App\Filament\Admin\Resources;

use App\Models\PaymentProof;
use App\Models\DetectionCode;
use App\Models\Order;
use App\Support\AdminNavigation;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class PaymentProofResource extends Resource
{
    protected static ?string $model = PaymentProof::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = '支付凭证审核';
    protected static \UnitEnum|string|null $navigationGroup = AdminNavigation::GROUP_PAYMENT;
    protected static ?int $navigationSort = AdminNavigation::ORDER_PAYMENT_PROOFS;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('编号')->sortable(),
                TextColumn::make('order_id')->label('订单号')->sortable(),
                TextColumn::make('order.user.display_name')->label('蜂农'),
                TextColumn::make('amount')->label('金额')->money('CNY', true)->sortable(),
                TextColumn::make('method')
                    ->label('提交方式')
                    ->badge(),
                TextColumn::make('trade_no')->label('凭证号')->toggleable(),
                TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'pending' => '待审核',
                        'approved' => '已通过',
                        'rejected' => '已驳回',
                        default => $state,
                    })
                    ->sortable(),
                TextColumn::make('created_at')->label('提交时间')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('状态')->options([
                    'pending' => '待审核',
                    'approved' => '已通过',
                    'rejected' => '已驳回',
                ]),
            ])
            ->actions([
                \Filament\Actions\Action::make('viewImages')
                    ->label('查看凭证')
                    ->modalHeading('支付凭证')
                    ->modalContent(fn (PaymentProof $record) => view('filament.payment-proof-images', ['images' => (array) ($record->images ?? [])]))
                    ->visible(fn (PaymentProof $record) => is_array($record->images) && count($record->images) > 0),

                \Filament\Actions\Action::make('approve')
                    ->label('通过审核')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (PaymentProof $record) => $record->status === 'pending')
                    ->modalDescription(function () {
                        $availableCount = DetectionCode::query()
                            ->where('source_type', 'self_paid')
                            ->where('status', 'available')
                            ->count();

                        if ($availableCount === 0) {
                            return '⚠️ 警告：当前没有可用的自费检测码，请先生成后再审核。';
                        } elseif ($availableCount < 10) {
                            return "⚠️ 提醒：仅剩 {$availableCount} 个可用自费检测码。";
                        }

                        return '确认通过此支付凭证？';
                    })
                    ->action(function (PaymentProof $record) {
                        DB::transaction(function () use ($record) {
                            $order = Order::query()->lockForUpdate()->findOrFail($record->order_id);
                            if ($order->status !== 'pending') {
                                abort(409, 'order_not_pending');
                            }

                            // pick available self-paid code
                            $code = DetectionCode::query()
                                ->where('source_type', 'self_paid')
                                ->where('status', 'available')
                                ->lockForUpdate()
                                ->orderBy('id')
                                ->first();
                            if (!$code) {
                            Notification::make()
                                ->title('审核失败')
                                ->body('无可用自费检测码，请先生成自费检测码')
                                ->danger()
                                ->persistent()
                                ->send();

                                return; // 直接返回，不继续执行
                            }

                            // conditional update
                            $updated = DetectionCode::query()
                                ->where('id', $code->id)
                                ->where('status', 'available')
                                ->update([
                                    'status' => 'assigned',
                                    'assigned_user_id' => $order->user_id,
                                    'assigned_at' => now(),
                                ]);
                            if ($updated < 1) {
                                abort(409, 'assign_conflict');
                            }

                            $order->update([
                                'status' => 'paid',
                                'paid_at' => now(),
                                'channel' => 'manual',
                                'detection_code_id' => $code->id,
                                'trade_no' => $record->trade_no,
                            ]);

                            $record->update([
                                'status' => 'approved',
                                'reviewed_by' => auth()->id(),
                                'reviewed_at' => now(),
                            ]);
                        });
                    }),

                \Filament\Actions\Action::make('reject')
                    ->label('驳回')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (PaymentProof $record) => $record->status === 'pending')
                    ->action(function (PaymentProof $record) {
                        $record->update([
                            'status' => 'rejected',
                            'reviewed_by' => auth()->id(),
                            'reviewed_at' => now(),
                        ]);
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Admin\Resources\PaymentProofResource\Pages\ListPaymentProofs::route('/'),
        ];
    }
}
