<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('expenses')) {
            return;
        }

        if (Schema::hasColumn('expenses', 'finance_category_id')) {
            return;
        }

        Schema::table('expenses', function (Blueprint $table) {
            $table->unsignedBigInteger('finance_category_id')->nullable()->after('category_id');
            $table->index('finance_category_id');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('expenses')) {
            return;
        }

        if (Schema::hasColumn('expenses', 'finance_category_id')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->dropColumn('finance_category_id');
            });
        }
    }
};
