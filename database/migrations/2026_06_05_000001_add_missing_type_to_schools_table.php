<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Backfill the 'type' column that was missed when domain_type already existed.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('schools', 'type')) {
            Schema::table('schools', static function (Blueprint $table) {
                $table->string('type')->after('code')->nullable()->default('custom');
            });

            // Set existing schools without a type to 'custom'
            DB::table('schools')->whereNull('type')->update(['type' => 'custom']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('schools', 'type')) {
            Schema::table('schools', static function (Blueprint $table) {
                $table->dropColumn('type');
            });
        }
    }
};
