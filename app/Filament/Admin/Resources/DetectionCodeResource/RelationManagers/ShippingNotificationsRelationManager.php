<?php

namespace App\Filament\Admin\Resources\DetectionCodeResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ShippingNotificationsRelationManager extends RelationManager
{
    protected static string $relationship = 'shippingNotifications';

    protected static ?string $title = 'Shipping Notifications';

    public function table(Table $table): Table
    {
        return $table
            ->recordUrl(fn ($record) => \App\Filament\Admin\Resources\ShippingNotificationResource::getUrl('view', ['record' => $record]))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('shipped_at')->date()->sortable(),
                TextColumn::make('courier_company')->label('Courier')->searchable()->sortable(),
                TextColumn::make('tracking_no')->label('Tracking No.')->searchable()->sortable(),
                TextColumn::make('user.display_name')->label('User'),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}

