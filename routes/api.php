<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\WeChatAuthController;
use App\Http\Controllers\Api\OrdersController;
use App\Http\Controllers\Api\OrderPaymentProofController;
use App\Http\Controllers\Api\UploadController;

Route::prefix('auth/wechat')->group(function () {
    Route::post('login', [WeChatAuthController::class, 'login']);
    Route::post('bind-phone', [WeChatAuthController::class, 'bindPhone'])->middleware('auth:sanctum');
});

Route::middleware('auth:sanctum')->group(function () {
    // Uploads (images)
    Route::post('uploads', [UploadController::class, 'store']);

    // Orders
    Route::post('orders', [OrdersController::class, 'store']);
    Route::get('orders/{order}', [OrdersController::class, 'show']);
    // Payment Proofs
    Route::post('orders/{order}/payment-proof', [OrderPaymentProofController::class, 'store']);
});
