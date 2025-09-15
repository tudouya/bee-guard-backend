<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Shipping\ShippingNotificationStoreRequest;
use App\Models\DetectionCode;
use App\Models\ShippingNotification;
use Illuminate\Database\Query\Exception as QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ShippingNotificationsController extends Controller
{
    public function store(ShippingNotificationStoreRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        // Normalize detection number (strip hyphens, uppercase)
        $full = strtoupper(str_replace('-', '', $data['detection_number']));

        // First: locate code by number regardless of ownership to decide 404 vs 403
        $code = DetectionCode::query()
            ->whereRaw('UPPER(CONCAT(prefix, code)) = ?', [$full])
            ->first();

        if (!$code) {
            return response()->json([
                'code' => 'detection_not_found',
                'message' => '检测号不存在',
            ], 404);
        }

        if ((int) $code->assigned_user_id !== (int) $user->id) {
            return response()->json([
                'code' => 'forbidden',
                'message' => '无权提交该检测号的邮寄信息',
            ], 403);
        }

        try {
            $sn = DB::transaction(function () use ($user, $code, $data) {
                // Idempotency check: detection_code_id + tracking_no
                $exists = ShippingNotification::query()
                    ->where('detection_code_id', $code->id)
                    ->where('tracking_no', $data['tracking_no'])
                    ->exists();
                if ($exists) {
                    return 'DUPLICATE';
                }

                return ShippingNotification::query()->create([
                    'user_id' => $user->id,
                    'detection_code_id' => $code->id,
                    'courier_company' => $data['courier_company'],
                    'tracking_no' => $data['tracking_no'],
                    'shipped_at' => $data['shipped_at'] ?? null,
                ]);
            });
        } catch (QueryException $e) {
            // Unique key safety net
            return response()->json([
                'code' => 'shipping_duplicate',
                'message' => '该检测已提交相同快递单号',
            ], 409);
        }

        if ($sn === 'DUPLICATE') {
            return response()->json([
                'code' => 'shipping_duplicate',
                'message' => '该检测已提交相同快递单号',
            ], 409);
        }

        return response()->json([
            'code' => 0,
            'message' => 'ok',
            'data' => [
                'id' => $sn->id,
                'userId' => $sn->user_id,
                'detectionId' => $sn->detection_code_id,
                'detectionNumber' => $code->prefix . $code->code,
                'courierCompany' => $sn->courier_company,
                'trackingNo' => $sn->tracking_no,
                'shippedAt' => $sn->shipped_at?->format('Y-m-d'),
                'createdAt' => $sn->created_at->toDateTimeString(),
            ],
        ], 201);
    }
}

