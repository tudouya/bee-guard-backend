<?php

namespace App\Filament\Admin\Resources\ShippingNotificationResource\Pages;

use App\Filament\Admin\Resources\ShippingNotificationResource;
use App\Filament\Admin\Resources\OrderResource;
use App\Filament\Admin\Resources\SurveyResource;
use App\Models\Order;
use App\Models\Survey;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewShippingNotification extends ViewRecord
{
    protected static string $resource = ShippingNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewSurvey')
                ->label('查看问卷')
                ->icon('heroicon-o-clipboard-document-check')
                ->visible(function () {
                    $record = $this->getRecord();
                    return Survey::query()->where('detection_code_id', $record->detection_code_id)->exists();
                })
                ->url(function () {
                    $record = $this->getRecord();
                    $survey = Survey::query()->where('detection_code_id', $record->detection_code_id)->latest('id')->first();
                    return $survey ? SurveyResource::getUrl('view', ['record' => $survey]) : null;
                }, shouldOpenInNewTab: true),

            Action::make('viewOrder')
                ->label('查看订单')
                ->icon('heroicon-o-rectangle-stack')
                ->visible(function () {
                    $record = $this->getRecord();
                    return Order::query()->where('detection_code_id', $record->detection_code_id)->exists();
                })
                ->url(function () {
                    $record = $this->getRecord();
                    $order = Order::query()->where('detection_code_id', $record->detection_code_id)->latest('id')->first();
                    return $order ? (OrderResource::getUrl('index') . '?tableSearch=' . $order->id) : null;
                }, shouldOpenInNewTab: true),
        ];
    }
}
