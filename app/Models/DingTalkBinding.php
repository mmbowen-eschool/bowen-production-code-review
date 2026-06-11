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

    /**
     * 绑定所属学校（schools 表在主库）。
     */
    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    /**
     * 遮罩 openId / unionId，只显示前 6 位 + **** + 后 4 位。
     */
    public static function maskOpenId(?string $value): string
    {
        if (empty($value)) {
            return '';
        }
        if (mb_strlen($value) <= 10) {
            return mb_substr($value, 0, 3) . '****' . mb_substr($value, -3);
        }
        return mb_substr($value, 0, 6) . '****' . mb_substr($value, -4);
    }
}
