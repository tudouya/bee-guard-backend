<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Orders\OrderStoreRequest;
use App\Models\Order;
use Illuminate\Http\JsonResponse;

class OrdersController extends Controller
{
    public function store(OrderStoreRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $order = Order::query()->create([
            'user_id' => $user->id,
            'amount' => $data['amount'],
            'status' => 'pending',
            'channel' => 'manual',
            'package_id' => $data['package_id'] ?? null,
            'package_name' => $data['package_name'] ?? null,
        ]);

        return response()->json([
            'code' => 0,
            'message' => 'ok',
            'data' => [
                'order_id' => $order->id,
                'amount' => $order->amount,
                'status' => $order->status,
            ],
        ]);
    }

    public function show(Order $order): JsonResponse
    {
        $this->authorizeOrder($order);

        return response()->json([
            'code' => 0,
            'message' => 'ok',
            'data' => [
                'id' => $order->id,
                'amount' => $order->amount,
                'status' => $order->status,
                'channel' => $order->channel,
                'trade_no' => $order->trade_no,
                'paid_at' => optional($order->paid_at)->toDateTimeString(),
                'detection_code' => $order->detectionCode?->code,
                'detection_prefix' => $order->detectionCode?->prefix,
            ],
        ]);
    }

    protected function authorizeOrder(Order $order): void
    {
        $user = request()->user();
        abort_if(!$user || $order->user_id !== $user->id, 403);
    }
}
