<?php

namespace App\Services\WeChat;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class WeChatMiniProgram
{
    public function code2Session(string $jsCode): array
    {
        $appid = config('services.wechat_mini.app_id');
        $secret = config('services.wechat_mini.app_secret');

        $resp = Http::asJson()->get('https://api.weixin.qq.com/sns/jscode2session', [
            'appid' => $appid,
            'secret' => $secret,
            'js_code' => $jsCode,
            'grant_type' => 'authorization_code',
        ]);

        return $resp->json() ?? [];
    }

    public function getAccessToken(): ?string
    {
        if ($cached = Cache::get('wechat:mini:access_token')) {
            return $cached;
        }

        $appid = config('services.wechat_mini.app_id');
        $secret = config('services.wechat_mini.app_secret');
        $resp = Http::get('https://api.weixin.qq.com/cgi-bin/token', [
            'grant_type' => 'client_credential',
            'appid' => $appid,
            'secret' => $secret,
        ])->json();

        if (!is_array($resp) || !isset($resp['access_token'])) {
            return null;
        }

        $ttl = max(60, ((int)($resp['expires_in'] ?? 7200)) - 300);
        Cache::put('wechat:mini:access_token', $resp['access_token'], now()->addSeconds($ttl));
        return $resp['access_token'];
    }

    public function getPhoneNumber(string $code): array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return ['errcode' => -1, 'errmsg' => 'access_token_unavailable'];
        }

        $resp = Http::withQueryParameters(['access_token' => $token])
            ->post('https://api.weixin.qq.com/wxa/business/getuserphonenumber', [
                'code' => $code,
            ]);

        return $resp->json() ?? [];
    }
}
