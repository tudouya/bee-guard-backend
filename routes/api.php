<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\WeChatAuthController;

Route::prefix('auth/wechat')->group(function () {
    Route::post('login', [WeChatAuthController::class, 'login']);
    Route::post('bind-phone', [WeChatAuthController::class, 'bindPhone'])->middleware('auth:sanctum');
});

