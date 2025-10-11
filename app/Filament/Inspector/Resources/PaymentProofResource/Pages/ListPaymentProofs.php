<?php

namespace App\Filament\Inspector\Resources\PaymentProofResource\Pages;

use App\Filament\Inspector\Resources\PaymentProofResource;
use App\Filament\Admin\Resources\PaymentProofResource\Pages\ListPaymentProofs as BaseListPaymentProofs;

class ListPaymentProofs extends BaseListPaymentProofs
{
    protected static string $resource = PaymentProofResource::class;
}
