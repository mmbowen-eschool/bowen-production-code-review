<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\DingTalkBinding;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DingTalkLoginController extends Controller
{
    private const AUTH_URL = 'https://login.dingtalk.com/oauth2/auth';
    private const TOKEN_URL = 'https://api.dingtalk.com/v1.0/oauth2/userAccessToken';
    private const USERINFO_URL = 'https://api.dingtalk.com/v1.0/contact/users/me';

    /**
     * Step 1: 生成 state，保存到 session，跳转钉钉授权页。
     *
     * 要求: ?school_code=XXX 必须存在且有效。
     */
    public function login(Request $request)
    {
        // 1. school_code 必须存在
        $rawSchoolCode = $request->query('school_code');

        if (empty($rawSchoolCode)) {
            return response('Missing school_code.', 400);
        }

        // 2. 在主库验证 school_code，获取完整学校信息
        $school = School::on('mysql')->where('code', $rawSchoolCode)->first();

        if (!$school) {
            return response('Invalid school_code.', 400);
        }

        // 3. 存入 session 的学校信息全部来自 DB 查询，不信任 raw input
        session([
            'dingtalk_school_id'            => $school->id,
            'dingtalk_school_code'          => $school->code,
            'dingtalk_school_database_name' => $school->database_name,
        ]);

        // 清除之前可能残留的 pending 绑定信息
        session()->forget([
            'dingtalk_pending_open_id',
            'dingtalk_pending_union_id',
            'dingtalk_pending_nick',
        ]);

        // 4. 生成 OAuth state 并跳转
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
     * Step 2: 钉钉回调，用 authCode 换取 userAccessToken，再获取用户信息。
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

        // 从 session 读取已验证的学校信息
        $schoolId   = session('dingtalk_school_id');
        $schoolCode = session('dingtalk_school_code');

        if (empty($schoolId)) {
            return response('DingTalk school session expired.', 400);
        }

        try {
            // 1. 用 authCode 换取 userAccessToken
            $tokenResponse = Http::asJson()->post(self::TOKEN_URL, [
                'clientId'     => config('services.dingtalk.client_id'),
                'clientSecret' => config('services.dingtalk.client_secret'),
                'code'         => $authCode,
                'grantType'    => 'authorization_code',
            ]);

            if (!$tokenResponse->successful()) {
                Log::error('DingTalk token request failed', [
                    'status' => $tokenResponse->status(),
                ]);
                return response('DingTalk user info fetch FAILED (token).', 500);
            }

            $tokenData = $tokenResponse->json();
            $accessToken = $tokenData['accessToken'] ?? null;

            if (empty($accessToken)) {
                Log::error('DingTalk token response missing accessToken');
                return response('DingTalk user info fetch FAILED (token).', 500);
            }

            // 2. 用 userAccessToken 获取用户信息
            // 钉钉要求 x-acs-dingtalk-access-token，不能用 Authorization: Bearer
            $userResponse = Http::withHeaders([
                'x-acs-dingtalk-access-token' => $accessToken,
                'Accept' => 'application/json',
            ])->get(self::USERINFO_URL);

            if (!$userResponse->successful()) {
                $errBody = $userResponse->json();
                Log::error('DingTalk user info request failed', [
                    'status'    => $userResponse->status(),
                    'err_code'  => $errBody['code'] ?? null,
                    'err_msg'   => $errBody['message'] ?? null,
                ]);
                return response('DingTalk user info fetch FAILED (userinfo).', 500);
            }

            $userData = $userResponse->json();
            $openId  = $userData['openId'] ?? null;
            $unionId = $userData['unionId'] ?? null;
            $nick    = $userData['nick'] ?? null;

            // 3. 查询绑定记录
            $binding = DingTalkBinding::where('dingtalk_open_id', $openId)->first();

            if ($binding) {
                // 已绑定，校验 school_id 是否匹配
                if ($binding->school_id != $schoolId) {
                    return response('This DingTalk account is not bound to this school.', 403);
                }

                $lines = [
                    'DingTalk binding found.',
                    'school_id: ' . ($binding->school_id ? 'YES' : 'NO'),
                    'user_id:   ' . ($binding->user_id ? 'YES' : 'NO'),
                ];
                return response(implode("\n", $lines));
            }

            // 4. 无绑定 → 保存钉钉信息到 session（school_id/school_code 来自已验证的 session）
            session([
                'dingtalk_pending_open_id'  => $openId,
                'dingtalk_pending_union_id' => $unionId,
                'dingtalk_pending_nick'     => $nick,
            ]);

            return redirect()->route('dingtalk.bind');

        } catch (\Throwable $e) {
            Log::error('DingTalk callback exception', [
                'stage'   => 'callback',
                'class'   => get_class($e),
                'code'    => $e->getCode(),
            ]);
            return response('DingTalk user info fetch FAILED (exception).', 500);
        }
    }

    /**
     * Step 3A (GET): 显示绑定表单。
     */
    public function bindForm(Request $request)
    {
        $pendingOpenId = session('dingtalk_pending_open_id');

        if (empty($pendingOpenId)) {
            return response('DingTalk session expired. Please re-enter from DingTalk.', 400);
        }

        $schoolCodeInSession = session('dingtalk_school_code');

        return view('auth.dingtalk-bind', [
            'schoolCodeInSession' => $schoolCodeInSession,
        ]);
    }

    /**
     * Step 3B (POST): 处理绑定逻辑。
     */
    public function bind(Request $request)
    {
        $request->validate([
            'email'    => 'required|string',
            'password' => 'required|string',
        ]);

        $pendingOpenId  = session('dingtalk_pending_open_id');
        $pendingUnionId = session('dingtalk_pending_union_id');
        $pendingNick    = session('dingtalk_pending_nick');
        // 只从 session 取学校信息，不信任 request 参数
        $schoolId       = session('dingtalk_school_id');
        $schoolCode     = session('dingtalk_school_code');

        if (empty($pendingOpenId)) {
            return redirect()->route('dingtalk.bind')
                ->with('error', 'DingTalk session expired. Please re-enter from DingTalk.');
        }

        if (empty($schoolId)) {
            return redirect()->route('dingtalk.bind')
                ->with('error', 'School session expired. Please re-enter with a valid school_code.');
        }

        // 1. 根据已验证的 school_id 在主库查找学校
        $school = School::on('mysql')->where('id', $schoolId)->first();

        if (!$school) {
            Log::warning('DingTalk bind: school not found by id', ['school_id' => $schoolId]);
            return redirect()->route('dingtalk.bind')
                ->with('error', 'School not found.');
        }

        // 2. 切换到学校数据库
        $previousConnection = DB::getDefaultConnection();
        Config::set('database.connections.school.database', $school->database_name);
        DB::purge('school');
        DB::reconnect('school');
        DB::setDefaultConnection('school');

        try {
            // 3. 在学校库查找用户（email 或 mobile）
            $loginValue = $request->input('email');
            $user = User::where('email', $loginValue)
                ->orWhere('mobile', $loginValue)
                ->first();

            if (!$user || !Hash::check($request->input('password'), $user->password)) {
                return redirect()->route('dingtalk.bind')
                    ->with('error', 'Invalid credentials.');
            }

            // 4. 必须是 Teacher 角色
            if (!$user->hasRole('Teacher')) {
                Log::warning('DingTalk bind: user is not a teacher');
                return redirect()->route('dingtalk.bind')
                    ->with('error', 'Only teacher accounts can bind with DingTalk.');
            }

            // 5a. 检查当前 DingTalk 账号是否已绑定其他学校
            $crossSchool = DingTalkBinding::where('dingtalk_open_id', $pendingOpenId)
                ->where('school_id', '!=', $school->id)
                ->first();

            if ($crossSchool) {
                Log::warning('DingTalk bind: openId already bound to another school', [
                    'other_school_id' => $crossSchool->school_id,
                ]);
                return redirect()->route('dingtalk.bind')
                    ->with('error', 'This DingTalk account is already bound to another school.');
            }

            // 5b. 检查当前 school_id + user_id 是否已被其他 DingTalk 账号绑定
            $sameUserOtherDingTalk = DingTalkBinding::where('school_id', $school->id)
                ->where('user_id', $user->id)
                ->where('dingtalk_open_id', '!=', $pendingOpenId)
                ->first();

            if ($sameUserOtherDingTalk) {
                Log::warning('DingTalk bind: eSchool user already bound to another DingTalk account');
                return redirect()->route('dingtalk.bind')
                    ->with('error', 'This eSchool account is already bound to another DingTalk account.');
            }

            // 6. 保存绑定记录到主库
            DingTalkBinding::create([
                'dingtalk_open_id'  => $pendingOpenId,
                'dingtalk_union_id' => $pendingUnionId,
                'school_id'         => $school->id,
                'school_code'       => $school->code,
                'user_id'           => $user->id,
                'dingtalk_nick'     => $pendingNick,
                'last_login_at'     => now(),
            ]);

            Log::info('DingTalk binding created', [
                'school_code' => $school->code,
                'school_id'   => $school->id,
                'user_id'     => $user->id,
            ]);

            // 清除所有 DingTalk session
            session()->forget([
                'dingtalk_pending_open_id',
                'dingtalk_pending_union_id',
                'dingtalk_pending_nick',
                'dingtalk_school_id',
                'dingtalk_school_code',
                'dingtalk_school_database_name',
            ]);

            return response('DingTalk binding created successfully.');

        } catch (\Throwable $e) {
            Log::error('DingTalk bind exception', [
                'stage'   => 'bind',
                'class'   => get_class($e),
                'code'    => $e->getCode(),
            ]);
            return redirect()->route('dingtalk.bind')
                ->with('error', 'Binding failed. Please try again later.');
        } finally {
            // 恢复默认数据库连接
            if ($previousConnection !== 'school') {
                DB::setDefaultConnection($previousConnection);
                DB::purge('school');
            }
        }
    }
}
