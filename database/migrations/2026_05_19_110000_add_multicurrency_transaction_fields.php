<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 新增字段列表
     */
    private const NEW_COLUMNS = [
        'transaction_currency' => "ADD COLUMN transaction_currency VARCHAR(3) DEFAULT 'MMK'",
        'original_amount' => "ADD COLUMN original_amount DECIMAL(12,2) DEFAULT 0",
        'exchange_rate_snapshot' => "ADD COLUMN exchange_rate_snapshot DECIMAL(12,4) DEFAULT 1",
        'amount_mmk' => "ADD COLUMN amount_mmk DECIMAL(12,2) DEFAULT 0",
    ];

    /**
     * 需要处理的表
     */
    private const TABLES = ['fees_paids', 'expenses'];

    /**
     * Run the migrations.
     * 
     * 遍历所有学校数据库，对 fees_paids 和 expenses 表添加多货币字段
     */
    public function up(): void
    {
        $this->info('开始多学校多数据库迁移...');

        // 获取主数据库名
        $mainDb = DB::connection()->getDatabaseName();
        $this->info("主数据库: {$mainDb}");

        // 从 information_schema.tables 找出所有包含 fees_paids 或 expenses 的学校数据库
        $schoolDatabases = $this->findSchoolDatabases();

        if (empty($schoolDatabases)) {
            $this->warn('未找到包含 fees_paids 或 expenses 表的学校数据库');
            return;
        }

        $this->info('找到 ' . count($schoolDatabases) . ' 个学校数据库');

        // 遍历每个学校数据库
        foreach ($schoolDatabases as $dbName) {
            $this->info("处理数据库: {$dbName}");

            foreach (self::TABLES as $table) {
                $this->processTableInDatabase($dbName, $table, 'up');
            }
        }

        $this->info('多学校多数据库迁移完成');
    }

    /**
     * Reverse the migrations.
     * 
     * 遍历所有学校数据库，删除多货币字段
     */
    public function down(): void
    {
        $this->info('开始回滚多学校多数据库迁移...');

        // 获取主数据库名
        $mainDb = DB::connection()->getDatabaseName();
        $this->info("主数据库: {$mainDb}");

        // 从 information_schema.tables 找出所有包含 fees_paids 或 expenses 的学校数据库
        $schoolDatabases = $this->findSchoolDatabases();

        if (empty($schoolDatabases)) {
            $this->warn('未找到包含 fees_paids 或 expenses 表的学校数据库');
            return;
        }

        $this->info('找到 ' . count($schoolDatabases) . ' 个学校数据库');

        // 遍历每个学校数据库
        foreach ($schoolDatabases as $dbName) {
            $this->info("处理数据库: {$dbName}");

            foreach (self::TABLES as $table) {
                $this->processTableInDatabase($dbName, $table, 'down');
            }
        }

        $this->info('多学校多数据库回滚完成');
    }

    /**
     * 从 information_schema.tables 找出所有包含 fees_paids 或 expenses 表的学校数据库
     * 排除主数据库和系统数据库
     */
    private function findSchoolDatabases(): array
    {
        $mainDb = DB::connection()->getDatabaseName();

        // 查询包含 fees_paids 或 expenses 表的数据库
        $sql = "
            SELECT DISTINCT TABLE_SCHEMA as db_name
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA != ?
              AND TABLE_SCHEMA NOT IN ('information_schema', 'mysql', 'performance_schema', 'sys')
              AND TABLE_NAME IN ('fees_paids', 'expenses')
            ORDER BY TABLE_SCHEMA
        ";

        $results = DB::select($sql, [$mainDb]);

        return array_column($results, 'db_name');
    }

    /**
     * 在指定数据库的指定表上执行 up 或 down 操作
     */
    private function processTableInDatabase(string $dbName, string $table, string $direction): void
    {
        // 检查表是否存在
        if (!$this->tableExistsInDatabase($dbName, $table)) {
            $this->warn("  表 {$dbName}.{$table} 不存在，跳过");
            return;
        }

        if ($direction === 'up') {
            $this->addColumnsToTable($dbName, $table);
        } else {
            $this->removeColumnsFromTable($dbName, $table);
        }
    }

    /**
     * 检查指定数据库中的表是否存在
     */
    private function tableExistsInDatabase(string $dbName, string $table): bool
    {
        $sql = "
            SELECT COUNT(*) as cnt
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
        ";

        $result = DB::selectOne($sql, [$dbName, $table]);

        return $result && $result->cnt > 0;
    }

    /**
     * 检查指定数据库的表中是否已存在某字段
     */
    private function columnExistsInTable(string $dbName, string $table, string $column): bool
    {
        $sql = "
            SELECT COUNT(*) as cnt
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
        ";

        $result = DB::selectOne($sql, [$dbName, $table, $column]);

        return $result && $result->cnt > 0;
    }

    /**
     * 为表添加多货币字段（幂等操作）
     */
    private function addColumnsToTable(string $dbName, string $table): void
    {
        $addedCount = 0;

        foreach (self::NEW_COLUMNS as $column => $alterSql) {
            // 如果字段已存在，跳过
            if ($this->columnExistsInTable($dbName, $table, $column)) {
                $this->info("  {$table}.{$column} 字段已存在，跳过");
                continue;
            }

            // 添加字段
            $fullSql = "ALTER TABLE `{$dbName}`.`{$table}` {$alterSql}";
            DB::statement($fullSql);
            $this->info("  添加字段: {$table}.{$column}");
            $addedCount++;
        }

        // 回填历史数据（只在有新增字段时执行）
        if ($addedCount > 0) {
            $this->backfillHistoricalData($dbName, $table);
        }
    }

    /**
     * 从表删除多货币字段（幂等操作）
     */
    private function removeColumnsFromTable(string $dbName, string $table): void
    {
        foreach (array_keys(self::NEW_COLUMNS) as $column) {
            // 如果字段不存在，跳过
            if (!$this->columnExistsInTable($dbName, $table, $column)) {
                $this->info("  {$table}.{$column} 字段不存在，跳过");
                continue;
            }

            // 删除字段
            $fullSql = "ALTER TABLE `{$dbName}`.`{$table}` DROP COLUMN `{$column}`";
            DB::statement($fullSql);
            $this->info("  删除字段: {$table}.{$column}");
        }
    }

    /**
     * 回填历史数据：将现有 amount 字段值复制到新字段
     */
    private function backfillHistoricalData(string $dbName, string $table): void
    {
        // 检查 amount 字段是否存在
        if (!$this->columnExistsInTable($dbName, $table, 'amount')) {
            $this->warn("  {$table}.amount 字段不存在，无法回填");
            return;
        }

        // 回填逻辑：MMK 交易，汇率 = 1
        $sql = "
            UPDATE `{$dbName}`.`{$table}`
            SET transaction_currency = 'MMK',
                original_amount = amount,
                exchange_rate_snapshot = 1,
                amount_mmk = amount
            WHERE transaction_currency IS NULL 
               OR transaction_currency = ''
               OR original_amount IS NULL
               OR amount_mmk IS NULL
        ";

        $affected = DB::affectingStatement($sql);
        $this->info("  回填 {$table} 历史数据: {$affected} 行");
    }

    /**
     * 输出信息（兼容 Laravel 命令行）
     */
    private function info(string $message): void
    {
        // 在 Laravel migrate 运行时使用日志或 echo
        if (app()->runningInConsole()) {
            echo $message . PHP_EOL;
        }
    }

    /**
     * 输出警告
     */
    private function warn(string $message): void
    {
        if (app()->runningInConsole()) {
            echo "⚠️  {$message}" . PHP_EOL;
        }
    }
};
