<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DetectionCode;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class DetectionCodesController extends Controller
{
    /**
     * Get user's detection codes
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $detectionCodes = DetectionCode::where('assigned_user_id', $user->id)
            ->orderBy('assigned_at', 'desc')
            ->get()
            ->map(function ($code) {
                return [
                    'id' => $code->id,
                    'full_code' => $code->prefix . $code->code,
                    'source_type' => $code->source_type,
                    'status' => $code->status,
                    'assigned_at' => $code->assigned_at?->format('Y-m-d H:i:s'),
                    'used_at' => $code->used_at?->format('Y-m-d H:i:s'),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $detectionCodes,
        ]);
    }

    /**
     * Verify detection code for current user
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string|regex:/^1[3-9]\d{9}$/',
            'detection_code' => 'required|string|max:64',
        ]);

        $user = $request->user();
        $phone = $request->input('phone');
        $inputCode = $request->input('detection_code');

        // Find detection code by matching prefix + code combination
        $detectionCode = DetectionCode::where('assigned_user_id', $user->id)
            ->whereRaw("CONCAT(prefix, code) = ?", [$inputCode])
            ->first();

        if (!$detectionCode) {
            throw ValidationException::withMessages([
                'detection_code' => ['检测号不存在或不属于当前用户'],
            ]);
        }

        // Check if code is expired
        if ($detectionCode->status === 'expired') {
            throw ValidationException::withMessages([
                'detection_code' => ['检测号已过期'],
            ]);
        }

        // Check if code is already used
        if ($detectionCode->status === 'used') {
            throw ValidationException::withMessages([
                'detection_code' => ['检测号已使用'],
            ]);
        }

        // Check if code is available for use (should be assigned)
        if ($detectionCode->status !== 'assigned') {
            throw ValidationException::withMessages([
                'detection_code' => ['检测号状态异常'],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => '检测号验证成功',
            'data' => [
                'detection_code_id' => $detectionCode->id,
                'full_code' => $detectionCode->prefix . $detectionCode->code,
                'source_type' => $detectionCode->source_type,
                'phone' => $phone,
            ],
        ]);
    }

    /**
     * Verify and bind detection code to current user atomically (available -> assigned).
     * Aligns with spec: verify-bind should both validate and perform binding.
     */
    public function verifyBind(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string|regex:/^1[3-9]\d{9}$/',
            'detection_number' => 'required|string|max:64',
        ]);

        $user = $request->user();
        $phone = $request->input('phone');
        $full = $request->input('detection_number');

        return DB::transaction(function () use ($user, $phone, $full) {
            // Try to bind if available
            $code = DetectionCode::whereRaw("CONCAT(prefix, code) = ?", [$full])
                ->lockForUpdate()
                ->first();

            if (!$code) {
                throw ValidationException::withMessages([
                    'detection_number' => ['检测号不存在'],
                ]);
            }

            if ($code->status === 'expired') {
                throw ValidationException::withMessages([
                    'detection_number' => ['检测号已过期'],
                ]);
            }

            if ($code->status === 'used') {
                throw ValidationException::withMessages([
                    'detection_number' => ['检测号已使用'],
                ]);
            }

            // If already assigned
            if ($code->status === 'assigned') {
                if ((int) $code->assigned_user_id !== (int) $user->id) {
                    throw ValidationException::withMessages([
                        'detection_number' => ['检测号不属于当前用户'],
                    ]);
                }
                // OK: already assigned to me
            } elseif ($code->status === 'available') {
                // Bind to current user
                $updated = DetectionCode::query()
                    ->where('id', $code->id)
                    ->where('status', 'available')
                    ->update([
                        'status' => 'assigned',
                        'assigned_user_id' => $user->id,
                        'assigned_at' => now(),
                    ]);
                if ($updated < 1) {
                    throw ValidationException::withMessages([
                        'detection_number' => ['检测号状态异常，请重试'],
                    ]);
                }
                // Refresh model state
                $code->refresh();
            }

            return response()->json([
                'success' => true,
                'message' => '检测号验证并绑定成功',
                'data' => [
                    'detection_code_id' => $code->id,
                    'full_code' => $code->prefix . $code->code,
                    'source_type' => $code->source_type,
                    'phone' => $phone,
                ],
            ]);
        });
    }
}
