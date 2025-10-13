<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\WeChatAuthController;
use App\Http\Controllers\Api\OrdersController;
use App\Http\Controllers\Api\OrderPaymentProofController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Admin\Community\PostController as AdminCommunityPostController;
use App\Http\Controllers\Admin\Community\ReplyController as AdminCommunityReplyController;
use App\Http\Controllers\Api\Community\LikeController as CommunityLikeController;
use App\Http\Controllers\Api\Community\PostController as CommunityPostController;
use App\Http\Controllers\Api\Community\ReplyController as CommunityReplyController;
use App\Http\Controllers\Api\DetectionCodesController;
use App\Http\Controllers\Api\SurveysController;
use App\Http\Controllers\Api\ShippingNotificationsController;
use App\Http\Controllers\Api\RewardController;
use App\Http\Controllers\Api\DetectionsController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\EnterpriseController;

// Knowledge base (public)
Route::prefix('knowledge')->group(function () {
    Route::get('/diseases', [\App\Http\Controllers\Api\Knowledge\DiseaseController::class, 'index']);
    Route::get('/diseases/{code}', [\App\Http\Controllers\Api\Knowledge\DiseaseController::class, 'show']);
    Route::get('/diseases/{code}/articles', [\App\Http\Controllers\Api\Knowledge\DiseaseController::class, 'articles']);

    Route::get('/articles/{id}', [\App\Http\Controllers\Api\Knowledge\ArticleController::class, 'show']);
    Route::post('/articles/{id}/exposure', [\App\Http\Controllers\Api\Knowledge\ArticleController::class, 'exposure']);
});

// Enterprises (public)
Route::get('enterprises', [EnterpriseController::class, 'index']);
Route::get('enterprises/{enterpriseId}', [EnterpriseController::class, 'show'])->whereNumber('enterpriseId');

Route::prefix('auth/wechat')->group(function () {
    Route::post('login', [WeChatAuthController::class, 'login']);
    Route::post('bind-phone', [WeChatAuthController::class, 'bindPhone'])->middleware('auth:sanctum');
});

Route::prefix('community')->group(function () {
    Route::get('posts', [CommunityPostController::class, 'index']);
    Route::get('posts/{id}', [CommunityPostController::class, 'show'])->whereNumber('id');
    Route::get('posts/{postId}/replies', [CommunityReplyController::class, 'index'])->whereNumber('postId');
});

Route::middleware('auth:sanctum')->group(function () {
    // Profile
    Route::get('profile', [ProfileController::class, 'show']);
    Route::patch('profile', [ProfileController::class, 'update']);
    // Optional alias to reduce client fallbacks
    Route::post('profile', [ProfileController::class, 'update']);

    // Uploads (images)
    Route::post('uploads', [UploadController::class, 'store']);

    // Orders
    Route::post('orders', [OrdersController::class, 'store']);
    Route::get('orders/{order}', [OrdersController::class, 'show']);
    // Payment Proofs
    Route::post('orders/{order}/payment-proof', [OrderPaymentProofController::class, 'store']);

    // Detection Codes
    Route::get('detection-codes', [DetectionCodesController::class, 'index']);
    Route::post('detection-codes/verify-bind', [DetectionCodesController::class, 'verifyBind']);

    // Surveys
    Route::post('surveys', [SurveysController::class, 'store']);

    // Shipping Notifications
    Route::post('shipping-notifications', [ShippingNotificationsController::class, 'store']);

    // Detections (results)
    Route::get('detections', [DetectionsController::class, 'index']);
    Route::get('detections/{id}', [DetectionsController::class, 'show']);

    Route::get('rewards', [RewardController::class, 'index']);
    Route::get('rewards/summary', [RewardController::class, 'summary']);
    Route::get('rewards/{id}', [RewardController::class, 'show'])->whereUuid('id');
    Route::post('rewards/{id}/acknowledge', [RewardController::class, 'acknowledge'])->whereUuid('id');
    Route::post('rewards/{id}/mark-used', [RewardController::class, 'markUsed'])->whereUuid('id');

    Route::prefix('community')->group(function () {
        Route::post('posts', [CommunityPostController::class, 'store']);
        Route::get('posts/mine', [CommunityPostController::class, 'mine']);
        Route::post('posts/{postId}/replies', [CommunityReplyController::class, 'store'])->whereNumber('postId');
        Route::post('posts/{postId}/like', [CommunityLikeController::class, 'store'])->whereNumber('postId');
        Route::delete('posts/{postId}/like', [CommunityLikeController::class, 'destroy'])->whereNumber('postId');
    });

    Route::prefix('admin/community')->group(function () {
        Route::get('posts', [AdminCommunityPostController::class, 'index']);
        Route::get('posts/{id}', [AdminCommunityPostController::class, 'show'])->whereNumber('id');
        Route::post('posts/{id}/approve', [AdminCommunityPostController::class, 'approve'])->whereNumber('id');
        Route::post('posts/{id}/reject', [AdminCommunityPostController::class, 'reject'])->whereNumber('id');
        Route::delete('posts/{id}', [AdminCommunityPostController::class, 'destroy'])->whereNumber('id');
        Route::post('posts/{id}/restore', [AdminCommunityPostController::class, 'restore'])->whereNumber('id');

        Route::get('replies', [AdminCommunityReplyController::class, 'index']);
        Route::post('replies/{id}/approve', [AdminCommunityReplyController::class, 'approve'])->whereNumber('id');
        Route::post('replies/{id}/reject', [AdminCommunityReplyController::class, 'reject'])->whereNumber('id');
        Route::delete('replies/{id}', [AdminCommunityReplyController::class, 'destroy'])->whereNumber('id');
        Route::post('replies/{id}/restore', [AdminCommunityReplyController::class, 'restore'])->whereNumber('id');
    });
});
