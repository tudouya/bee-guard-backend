<?php

namespace App\Filament\Admin\Resources;

use App\Models\PaymentProof;
use App\Models\DetectionCode;
use App\Models\Order;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class PaymentProofResource extends Resource
{
    protected static ?string $model = PaymentProof::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Payment Proofs';
    protected static \UnitEnum|string|null $navigationGroup = 'Business';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('order_id')->label('Order')->sortable(),
                TextColumn::make('order.user.display_name')->label('User'),
                TextColumn::make('amount')->money('CNY', true)->sortable(),
                TextColumn::make('method')->badge(),
                TextColumn::make('trade_no')->label('Pay Trade No')->toggleable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'pending' => 'Pending',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                ]),
            ])
            ->actions([
                \Filament\Actions\Action::make('viewImages')
                    ->label('Images')
                    ->modalHeading('Payment Images')
                    ->modalContent(fn (PaymentProof $record) => view('filament.payment-proof-images', ['images' => (array) ($record->images ?? [])]))
                    ->visible(fn (PaymentProof $record) => is_array($record->images) && count($record->images) > 0),

                \Filament\Actions\Action::make('approve')
                    ->label('Approve')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (PaymentProof $record) => $record->status === 'pending')
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
                                abort(409, 'no_available_code');
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
                    ->label('Reject')
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
