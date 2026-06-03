<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 为所有学校数据库的 fees_class_types 表添加多货币字段
     */
    public function up(): void
    {
        // 从 information_schema 获取所有学校数据库
        $schoolDatabases = $this->getSchoolDatabases();

        foreach ($schoolDatabases as $database) {
            $this->addFieldsToFeesClassTypes($database);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schoolDatabases = $this->getSchoolDatabases();

        foreach ($schoolDatabases as $database) {
            $this->removeFieldsFromFeesClassTypes($database);
        }
    }

    /**
     * 获取所有学校数据库
     */
    private function getSchoolDatabases(): array
    {
        // 主数据库名
        $mainDb = 'sql_43_160_241_126';

        // 查询 information_schema 获取包含 fees_class_types 表的学校数据库
        $results = DB::select("
            SELECT TABLE_SCHEMA as db_name
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA != ?
            AND TABLE_NAME = 'fees_class_types'
        ", [$mainDb]);

        return array_column($results, 'db_name');
    }

    /**
     * 添加字段到 fees_class_types 表
     */
    private function addFieldsToFeesClassTypes(string $database): void
    {
        $fields = [
            'fee_currency VARCHAR(3) DEFAULT \'MMK\'',
            'fee_original_amount DECIMAL(12,2) DEFAULT 0',
            'fee_exchange_rate_snapshot DECIMAL(12,4) DEFAULT 1.0000',
            'fee_amount_mmk DECIMAL(12,2) DEFAULT 0'
        ];

        foreach ($fields as $field) {
            // 提取字段名
            preg_match('/^(\S+)\s+/', $field, $matches);
            $fieldName = $matches[1];

            // 检查字段是否已存在
            $exists = DB::select("
                SELECT COUNT(*) as cnt
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = ?
                AND TABLE_NAME = 'fees_class_types'
                AND COLUMN_NAME = ?
            ", [$database, $fieldName]);

            if ($exists[0]->cnt == 0) {
                // 字段不存在，添加
                DB::statement("ALTER TABLE `{$database}`.`fees_class_types` ADD COLUMN {$field}");
            }
        }

        // 回填历史数据：将现有的 amount 值填充到新字段
        // 只更新 fee_original_amount, fee_amount_mmk 为 NULL 的记录
        DB::statement("
            UPDATE `{$database}`.`fees_class_types`
            SET fee_currency = 'MMK',
                fee_original_amount = amount,
                fee_exchange_rate_snapshot = 1.0000,
                fee_amount_mmk = amount
            WHERE fee_currency IS NULL
            OR fee_original_amount IS NULL
            OR fee_amount_mmk IS NULL
        ");
    }

    /**
     * 从 fees_class_types 表删除字段
     */
    private function removeFieldsFromFeesClassTypes(string $database): void
    {
        $fields = ['fee_amount_mmk', 'fee_exchange_rate_snapshot', 'fee_original_amount', 'fee_currency'];

        foreach ($fields as $field) {
            // 检查字段是否存在
            $exists = DB::select("
                SELECT COUNT(*) as cnt
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = ?
                AND TABLE_NAME = 'fees_class_types'
                AND COLUMN_NAME = ?
            ", [$database, $field]);

            if ($exists[0]->cnt > 0) {
                DB::statement("ALTER TABLE `{$database}`.`fees_class_types` DROP COLUMN {$field}");
            }
        }
    }
};
