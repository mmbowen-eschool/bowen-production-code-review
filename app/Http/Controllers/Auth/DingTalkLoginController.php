<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DingTalkLoginController extends Controller
{
    /**
     * OAuth 授权地址（钉钉官方）
     */
    private const AUTH_URL = 'https://login.dingtalk.com/oauth2/auth';

    /**
     * Step 1: 生成 state，保存到 session，跳转钉钉授权页。
     */
    public function login(Request $request)
    {
        $state = Str::random(32);
        session(['dingtalk_oauth_state' => $state]);

        $query = http_build_query([
            'redirect_uri'  => config('services.dingtalk.redirect_uri'),
            'response_type' => 'code',
            'client_id'     => config('services.dingtalk.client_id'),
            'scope'         => 'openid',
            'state'         => $state,
            'prompt'        => 'consent',
        ]);

        return redirect(self::AUTH_URL . '?' . $query);
    }

    /**
     * Step 2: 钉钉回调，验证 state 并展示 authCode。
     */
    public function callback(Request $request)
    {
        $authCode = $request->input('authCode');
        $state    = $request->input('state');

        $savedState = session('dingtalk_oauth_state');
        session()->forget('dingtalk_oauth_state');

        if (empty($state) || $state !== $savedState) {
            return response('State mismatch – possible CSRF attack.', 400);
        }

        if (empty($authCode)) {
            return response('Missing authCode from DingTalk callback.', 400);
        }

        // Phase 1: 仅展示收到 authCode，不做任何登录操作
        return response('DingTalk OAuth callback OK. authCode received (length: ' . strlen($authCode) . ').');
    }
}
