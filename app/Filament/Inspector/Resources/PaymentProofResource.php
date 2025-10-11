<?php

namespace App\Filament\Inspector\Resources;

use App\Support\InspectorNavigation;
use App\Filament\Inspector\Resources\PaymentProofResource\Pages;

class PaymentProofResource extends \App\Filament\Admin\Resources\PaymentProofResource
{
    protected static \UnitEnum|string|null $navigationGroup = InspectorNavigation::GROUP_PAYMENT;
    protected static ?int $navigationSort = InspectorNavigation::ORDER_PAYMENT_PROOFS;

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaymentProofs::route('/'),
        ];
    }
}
