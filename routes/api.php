<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\WeChatAuthController;
use App\Http\Controllers\Api\OrdersController;
use App\Http\Controllers\Api\OrderPaymentProofController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\DetectionCodesController;
use App\Http\Controllers\Api\SurveysController;
use App\Http\Controllers\Api\ShippingNotificationsController;

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

    // Detection Codes
    Route::get('detection-codes', [DetectionCodesController::class, 'index']);
    Route::post('detection-codes/verify', [DetectionCodesController::class, 'verify']);
    Route::post('detection-codes/verify-bind', [DetectionCodesController::class, 'verifyBind']);

    // Surveys
    Route::post('surveys', [SurveysController::class, 'store']);

    // Shipping Notifications
    Route::post('shipping-notifications', [ShippingNotificationsController::class, 'store']);
});
