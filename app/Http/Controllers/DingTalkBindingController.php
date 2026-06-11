<?php

namespace App\Http\Controllers;

use App\Models\DingTalkBinding;
use App\Models\School;
use App\Services\ResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DingTalkBindingController extends Controller
{
    /**
     * 绑定管理页面。
     */
    public function index()
    {
        if (!Auth::user()->hasRole('Super Admin') && !Auth::user()->hasRole('School Admin')) {
            abort(403);
        }

        return view('dingtalk.bindings');
    }

    /**
     * Bootstrap Table JSON 数据。
     */
    public function list(Request $request)
    {
        if (!Auth::user()->hasRole('Super Admin') && !Auth::user()->hasRole('School Admin')) {
            return ResponseService::errorResponse('Permission denied');
        }

        $offset = (int) $request->input('offset', 0);
        $limit  = (int) $request->input('limit', 10);
        $sort   = $request->input('sort', 'id');
        $order  = $request->input('order', 'DESC');
        $search = $request->input('search', '');

        // 基础查询 —— 显式使用主库连接
        $query = DingTalkBinding::on('mysql')
            ->with('school:id,name,code,database_name');

        // 权限过滤：School Admin 只能看本校
        if (!Auth::user()->hasRole('Super Admin')) {
            $schoolId = Auth::user()->school_id ?? session('school_id');
            if ($schoolId) {
                $query->where('school_id', $schoolId);
            } else {
                // 没有 school_id 的 School Admin 返回空
                return response()->json(['total' => 0, 'rows' => []]);
            }
        }

        // 搜索
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('dingtalk_open_id', 'LIKE', "%{$search}%")
                  ->orWhere('dingtalk_union_id', 'LIKE', "%{$search}%")
                  ->orWhere('dingtalk_nick', 'LIKE', "%{$search}%")
                  ->orWhere('school_code', 'LIKE', "%{$search}%");
            });
        }

        // 排序
        $allowedSort = ['id', 'school_id', 'created_at', 'last_login_at', 'dingtalk_nick'];
        if (!in_array($sort, $allowedSort)) {
            $sort = 'id';
        }
        $query->orderBy($sort, $order === 'ASC' ? 'ASC' : 'DESC');

        $total = $query->count();

        // 修正 offset
        if ($offset >= $total && $total > 0) {
            $offset = (int) floor(($total - 1) / $limit) * $limit;
        }

        $bindings = $query->skip($offset)->take($limit)->get();

        // 按 school_id 分组，批量从学校库查用户
        $usersBySchool = $this->fetchUsersGroupedBySchool($bindings);

        $rows = [];
        foreach ($bindings as $binding) {
            $school = $binding->school;
            $schoolId   = (int) $binding->school_id;
            $userId     = (int) $binding->user_id;

            $userInfo = $usersBySchool[$schoolId][$userId] ?? null;

            $rows[] = [
                'id'                        => $binding->id,
                'school_id'                 => $schoolId,
                'school_name'               => $school->name ?? 'Unknown School',
                'school_code'               => $binding->school_code ?? ($school->code ?? ''),
                'user_id'                   => $userId,
                'user_name'                 => $userInfo['name'] ?? 'Unknown User',
                'user_email'                => $userInfo['email'] ?? '',
                'dingtalk_open_id_masked'   => DingTalkBinding::maskOpenId($binding->dingtalk_open_id),
                'dingtalk_union_id_masked'  => DingTalkBinding::maskOpenId($binding->dingtalk_union_id),
                'dingtalk_nick'             => $binding->dingtalk_nick ?? '',
                'last_login_at'             => $binding->last_login_at,
                'created_at'                => $binding->created_at,
                'operate'                   => $this->operateHtml($binding->id),
            ];
        }

        return response()->json([
            'total' => $total,
            'rows'  => $rows,
        ]);
    }

    /**
     * 解绑：DELETE /dingtalk/bindings/{id}
     */
    public function destroy($id)
    {
        if (!Auth::user()->hasRole('Super Admin') && !Auth::user()->hasRole('School Admin')) {
            return ResponseService::errorResponse('Permission denied');
        }

        $binding = DingTalkBinding::on('mysql')->find($id);

        if (!$binding) {
            return ResponseService::errorResponse('Binding not found');
        }

        // School Admin 只能解绑本校
        if (!Auth::user()->hasRole('Super Admin')) {
            $mySchoolId = Auth::user()->school_id ?? session('school_id');
            if ((int) $binding->school_id !== (int) $mySchoolId) {
                return ResponseService::errorResponse('Cannot unbind other school records');
            }
        }

        // 只删除 dingtalk_bindings 记录
        $binding->delete();

        Log::info('DingTalk binding removed by admin', [
            'admin_id'   => Auth::id(),
            'binding_id' => $id,
            'school_id'  => $binding->school_id,
            'user_id'    => $binding->user_id,
            'open_id'    => DingTalkBinding::maskOpenId($binding->dingtalk_open_id),
        ]);

        return ResponseService::successResponse('DingTalk binding removed.');
    }

    /**
     * 按 school 批量查用户：{ school_id => { user_id => [name, email] } }
     */
    private function fetchUsersGroupedBySchool($bindings): array
    {
        $grouped = [];
        foreach ($bindings as $b) {
            $sid = (int) $b->school_id;
            $uid = (int) $b->user_id;
            if ($sid && $uid) {
                $grouped[$sid][] = $uid;
            }
        }

        if (empty($grouped)) {
            return [];
        }

        // 从主库查所有相关学校的 database_name
        $schoolIds = array_keys($grouped);
        $schools = School::on('mysql')->whereIn('id', $schoolIds)
            ->pluck('database_name', 'id')
            ->toArray();

        $result = [];

        // 保存当前连接，逐个学校库查用户
        $previousConnection = DB::getDefaultConnection();
        $previousSchoolDb   = Config::get('database.connections.school.database');

        try {
            foreach ($grouped as $schoolId => $userIds) {
                $dbName = $schools[$schoolId] ?? null;
                if (empty($dbName)) {
                    continue;
                }

                Config::set('database.connections.school.database', $dbName);
                DB::purge('school');
                DB::reconnect('school');

                $users = DB::connection('school')
                    ->table('users')
                    ->whereIn('id', array_unique($userIds))
                    ->select('id', 'first_name', 'last_name', 'email')
                    ->get();

                foreach ($users as $u) {
                    $result[$schoolId][$u->id] = [
                        'name'  => trim($u->first_name . ' ' . $u->last_name),
                        'email' => $u->email ?? '',
                    ];
                }
            }
        } finally {
            // 恢复学校库连接
            Config::set('database.connections.school.database', $previousSchoolDb);
            DB::purge('school');
            DB::reconnect('school');

            // 恢复默认连接
            if ($previousConnection !== 'school') {
                DB::setDefaultConnection($previousConnection);
            }
        }

        return $result;
    }

    /**
     * 操作列 HTML。
     */
    private function operateHtml($bindingId): string
    {
        return '<button class="btn btn-xs btn-gradient-danger btn-rounded btn-icon unbind-dingtalk-btn"'
            . ' data-id="' . $bindingId . '"'
            . ' title="' . trans('Unbind') . '">'
            . '<i class="fa fa-unlink"></i>'
            . '</button>';
    }
}
