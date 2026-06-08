<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DingTalkBinding extends Model
{
    /**
     * 绑定表固定放在主库，不加 school 库。
     */
    protected $connection = 'mysql';

    /**
     * 显式指定表名，避免 Laravel 自动推导为 ding_talk_bindings。
     */
    protected $table = 'dingtalk_bindings';

    protected $fillable = [
        'dingtalk_open_id',
        'dingtalk_union_id',
        'school_id',
        'school_code',
        'user_id',
        'dingtalk_nick',
        'last_login_at',
    ];
}
