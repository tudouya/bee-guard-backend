<?php

namespace App\Filament\Admin\Resources;

use App\Models\Order;
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
    protected static ?string $navigationLabel = 'Orders';
    protected static \UnitEnum|string|null $navigationGroup = 'Business';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]); // 只读展示，用表格即可
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('user.display_name')->label('User'),
                TextColumn::make('amount')->sortable()->money('CNY', true),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('channel')->badge()->sortable(),
                TextColumn::make('paid_at')->dateTime()->sortable(),
                TextColumn::make('detectionCode.prefix')->label('Prefix')->toggleable(),
                TextColumn::make('detectionCode.code')->label('Code')->toggleable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'pending' => 'Pending',
                    'paid' => 'Paid',
                    'failed' => 'Failed',
                    'refunded' => 'Refunded',
                ]),
                SelectFilter::make('channel')->options([
                    'manual' => 'Manual',
                    'wxpay' => 'WeChat Pay',
                    'alipay' => 'Alipay',
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

