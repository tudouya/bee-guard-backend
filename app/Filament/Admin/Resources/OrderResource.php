<?php

namespace App\Filament\Admin\Resources;

use App\Models\Order;
use App\Support\AdminNavigation;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = '订单管理';
    protected static \UnitEnum|string|null $navigationGroup = AdminNavigation::GROUP_PAYMENT;
    protected static ?int $navigationSort = AdminNavigation::ORDER_ORDERS;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]); // 只读展示，用表格即可
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('订单号')->sortable(),
                TextColumn::make('user.display_name')->label('蜂农')->default('—'),
                TextColumn::make('amount')->label('金额')->sortable()->money('CNY', true),
                TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'pending' => '待审核',
                        'paid' => '已支付',
                        'failed' => '支付失败',
                        'refunded' => '已退款',
                        default => $state,
                    })
                    ->sortable(),
                TextColumn::make('channel')
                    ->label('支付渠道')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'manual' => '人工审核',
                        'wxpay' => '微信支付',
                        'alipay' => '支付宝',
                        default => $state,
                    })
                    ->sortable(),
                TextColumn::make('paid_at')->label('支付时间')->date('Y-m-d')->sortable(),
                TextColumn::make('detectionCode.prefix')->label('检测号前缀')->toggleable(),
                TextColumn::make('detectionCode.code')->label('检测号')->toggleable(),
                TextColumn::make('created_at')->label('创建时间')->date('Y-m-d')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('状态')->options([
                    'pending' => '待审核',
                    'paid' => '已支付',
                    'failed' => '支付失败',
                    'refunded' => '已退款',
                ]),
                SelectFilter::make('channel')->label('支付渠道')->options([
                    'manual' => '人工审核',
                    'wxpay' => '微信支付',
                    'alipay' => '支付宝',
                ]),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Admin\Resources\OrderResource\Pages\ListOrders::route('/'),
        ];
    }
}
