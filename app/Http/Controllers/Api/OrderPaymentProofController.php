<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Orders\PaymentProofStoreRequest;
use App\Models\Order;
use App\Models\PaymentProof;
use App\Models\Upload;
use Illuminate\Http\JsonResponse;

class OrderPaymentProofController extends Controller
{
    public function store(PaymentProofStoreRequest $request, Order $order): JsonResponse
    {
        $user = $request->user();
        abort_if($order->user_id !== $user->id, 403);
        abort_if($order->status !== 'pending', 409, 'order_not_pending');

        $existsPending = PaymentProof::query()
            ->where('order_id', $order->id)
            ->where('status', 'pending')
            ->exists();
        abort_if($existsPending, 409, 'exists_pending_proof');

        $data = $request->validated();
        $images = $this->resolveImagesToPaths((array)($data['images'] ?? []));

        $proof = PaymentProof::query()->create([
            'order_id' => $order->id,
            'method' => (string) $data['method'],
            'trade_no' => (string) $data['trade_no'],
            'amount' => (float) $data['amount'],
            'images' => $images,
            'remark' => $data['remark'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json([
            'code' => 0,
            'message' => 'ok',
            'data' => [ 'proof_id' => $proof->id, 'status' => $proof->status ],
        ]);
    }

    /**
     * @param  array<int, mixed>  $images
     * @return array<int, string>
     */
    protected function resolveImagesToPaths(array $images): array
    {
        $paths = [];
        foreach ($images as $img) {
            if (is_numeric($img)) {
                $upload = Upload::query()->find((int) $img);
                if ($upload && $upload->path) {
                    $paths[] = (string) $upload->path;
                }
            } elseif (is_string($img) && $img !== '') {
                // 尽可能从 /storage/ 前缀还原到磁盘相对路径
                $str = (string) $img;
                $prefix = '/storage/';
                $pos = strpos($str, $prefix);
                if ($pos !== false) {
                    $str = substr($str, $pos + strlen($prefix));
                }
                $paths[] = $str;
            }
        }
        return array_values(array_filter($paths));
    }
}
