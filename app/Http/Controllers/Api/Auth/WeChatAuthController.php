<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\WeChatBindPhoneRequest;
use App\Http\Requests\Api\Auth\WeChatLoginRequest;
use App\Models\User;
use App\Services\WeChat\WeChatMiniProgram;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class WeChatAuthController extends Controller
{
    public function login(WeChatLoginRequest $request, WeChatMiniProgram $weapp): JsonResponse
    {
        $code = $request->validated('code');
        $resp = $weapp->code2Session($code);

        if (!isset($resp['openid'])) {
            return response()->json([
                'code' => $resp['errcode'] ?? 500,
                'message' => $resp['errmsg'] ?? 'wechat_login_failed',
            ], 400);
        }

        $user = User::query()->updateOrCreate(
            ['openid' => $resp['openid']],
            [
                'role' => 'farmer',
                'unionid' => $resp['unionid'] ?? null,
            ]
        );

        $token = $user->createToken('weapp')->plainTextToken;

        return response()->json([
            'code' => 0,
            'message' => 'ok',
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'role' => $user->role,
                    'openid' => $user->openid,
                    'nickname' => $user->nickname,
                    'avatar' => $user->avatar,
                    'phone' => $user->phone,
                ],
            ],
        ]);
    }

    public function bindPhone(WeChatBindPhoneRequest $request, WeChatMiniProgram $weapp): JsonResponse
    {
        $code = $request->validated('phone_code');
        $resp = $weapp->getPhoneNumber($code);

        if (!isset($resp['errcode']) || $resp['errcode'] !== 0) {
            return response()->json([
                'code' => $resp['errcode'] ?? 500,
                'message' => $resp['errmsg'] ?? 'get_phone_failed',
            ], 400);
        }

        $phoneInfo = $resp['phone_info'] ?? [];
        $phone = $phoneInfo['purePhoneNumber'] ?? ($phoneInfo['phoneNumber'] ?? null);

        $user = $request->user();
        if ($phone) {
            $user->phone = $phone;
            $user->save();
        }

        return response()->json([
            'code' => 0,
            'message' => 'ok',
            'data' => [ 'phone' => $phone ],
        ]);
    }
}
