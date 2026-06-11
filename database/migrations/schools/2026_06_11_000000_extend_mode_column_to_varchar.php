<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'school';

    /**
     * Run the migrations.
     * 将 compulsory_fees.mode 和 optional_fees.mode 从 ENUM 改为 VARCHAR(50)，
     * 以支持 Cash / Cheque / KBZ Pay / Quick Pay / KBZ Bank / AYA Bank /
     * YOMA BANK / CB Bank / Wechat Pay / Ali Pay 等更多支付方式。
     */
    public function up(): void
    {
        // 使用 raw SQL 确保兼容 MySQL 各版本，不依赖 doctrine/dbal
        DB::statement("ALTER TABLE compulsory_fees MODIFY COLUMN mode VARCHAR(50) NOT NULL DEFAULT 'Cash'");
        DB::statement("ALTER TABLE optional_fees MODIFY COLUMN mode VARCHAR(50) NOT NULL DEFAULT 'Cash'");
    }

    /**
     * Reverse the migrations.
     * 回滚时恢复为原始 ENUM 定义。
     * 注意：如果已有新支付方式的记录（如 KBZ Pay），回滚会因值不匹配而失败。
     * 如需回滚，请先手动清理新支付方式的记录或临时改用更大的 ENUM 集合。
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE compulsory_fees MODIFY COLUMN mode ENUM('Cash', 'Cheque', 'Online') NOT NULL DEFAULT 'Cash'");
        DB::statement("ALTER TABLE optional_fees MODIFY COLUMN mode ENUM('Cash', 'Cheque', 'Online') NOT NULL DEFAULT 'Cash'");
    }
};
