<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 货币显示设置
        DB::table('system_settings')->updateOrInsert(
            ['name' => 'display_currency'],
            ['data' => 'MMK', 'type' => 'system']
        );

        // USD 汇率：1 USD = 多少 MMK
        DB::table('system_settings')->updateOrInsert(
            ['name' => 'usd_exchange_rate'],
            ['data' => '3500', 'type' => 'system']
        );

        // CNY 汇率：1 CNY = 多少 MMK
        DB::table('system_settings')->updateOrInsert(
            ['name' => 'cny_exchange_rate'],
            ['data' => '500', 'type' => 'system']
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('system_settings')
            ->whereIn('name', ['display_currency', 'usd_exchange_rate', 'cny_exchange_rate'])
            ->delete();
    }
};
