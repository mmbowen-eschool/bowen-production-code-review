<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'school';

    public function up(): void
    {
        // fees_paids 表
        if (Schema::connection('school')->hasTable('fees_paids')) {
            $this->addColumnsToFeesPaids();
        }

        // expenses 表
        if (Schema::connection('school')->hasTable('expenses')) {
            $this->addColumnsToExpenses();
        }
    }

    public function down(): void
    {
        if (Schema::connection('school')->hasTable('fees_paids')) {
            Schema::connection('school')->table('fees_paids', function (Blueprint $table) {
                $this->dropColumns($table, ['transaction_currency', 'original_amount', 'exchange_rate_snapshot', 'amount_mmk']);
            });
        }

        if (Schema::connection('school')->hasTable('expenses')) {
            Schema::connection('school')->table('expenses', function (Blueprint $table) {
                $this->dropColumns($table, ['transaction_currency', 'original_amount', 'exchange_rate_snapshot', 'amount_mmk']);
            });
        }
    }

    private function addColumnsToFeesPaids(): void
    {
        Schema::connection('school')->table('fees_paids', function (Blueprint $table) {
            if (!Schema::connection('school')->hasColumn('fees_paids', 'transaction_currency')) {
                $table->string('transaction_currency', 3)->default('MMK')->after('amount');
            }
            if (!Schema::connection('school')->hasColumn('fees_paids', 'original_amount')) {
                $table->decimal('original_amount', 12, 2)->default(0)->nullable()->after('transaction_currency');
            }
            if (!Schema::connection('school')->hasColumn('fees_paids', 'exchange_rate_snapshot')) {
                $table->decimal('exchange_rate_snapshot', 12, 4)->default(1)->nullable()->after('original_amount');
            }
            if (!Schema::connection('school')->hasColumn('fees_paids', 'amount_mmk')) {
                $table->decimal('amount_mmk', 12, 2)->default(0)->nullable()->after('exchange_rate_snapshot');
            }
        });

        // 回填历史数据（仅针对空值）
        $this->backfillFeesPaids();
    }

    private function addColumnsToExpenses(): void
    {
        Schema::connection('school')->table('expenses', function (Blueprint $table) {
            if (!Schema::connection('school')->hasColumn('expenses', 'transaction_currency')) {
                $table->string('transaction_currency', 3)->default('MMK')->after('amount');
            }
            if (!Schema::connection('school')->hasColumn('expenses', 'original_amount')) {
                $table->decimal('original_amount', 12, 2)->default(0)->nullable()->after('transaction_currency');
            }
            if (!Schema::connection('school')->hasColumn('expenses', 'exchange_rate_snapshot')) {
                $table->decimal('exchange_rate_snapshot', 12, 4)->default(1)->nullable()->after('original_amount');
            }
            if (!Schema::connection('school')->hasColumn('expenses', 'amount_mmk')) {
                $table->decimal('amount_mmk', 12, 2)->default(0)->nullable()->after('exchange_rate_snapshot');
            }
        });

        $this->backfillExpenses();
    }

    private function backfillFeesPaids(): void
    {
        DB::connection('school')->table('fees_paids')
            ->where(function ($q) {
                $q->whereNull('original_amount')->orWhere('original_amount', 0);
            })
            ->orWhere(function ($q) {
                $q->whereNull('amount_mmk')->orWhere('amount_mmk', 0);
            })
            ->update([
                'transaction_currency'   => 'MMK',
                'original_amount'        => DB::raw('COALESCE(NULLIF(original_amount,0), amount)'),
                'exchange_rate_snapshot' => DB::raw('COALESCE(NULLIF(exchange_rate_snapshot,0), 1)'),
                'amount_mmk'             => DB::raw('COALESCE(NULLIF(amount_mmk,0), amount)'),
            ]);
    }

    private function backfillExpenses(): void
    {
        DB::connection('school')->table('expenses')
            ->where(function ($q) {
                $q->whereNull('original_amount')->orWhere('original_amount', 0);
            })
            ->orWhere(function ($q) {
                $q->whereNull('amount_mmk')->orWhere('amount_mmk', 0);
            })
            ->update([
                'transaction_currency'   => 'MMK',
                'original_amount'        => DB::raw('COALESCE(NULLIF(original_amount,0), amount)'),
                'exchange_rate_snapshot' => DB::raw('COALESCE(NULLIF(exchange_rate_snapshot,0), 1)'),
                'amount_mmk'             => DB::raw('COALESCE(NULLIF(amount_mmk,0), amount)'),
            ]);
    }

    private function dropColumns(Blueprint $table, array $columns): void
    {
        foreach ($columns as $column) {
            if (Schema::connection('school')->hasColumn($table->getTable(), $column)) {
                $table->dropColumn($column);
            }
        }
    }
};
