<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('fees_class_types')) {
            return;
        }

        if (Schema::hasColumn('fees_class_types', 'finance_category_id')) {
            return;
        }

        Schema::table('fees_class_types', function (Blueprint $table) {
            $table->unsignedBigInteger('finance_category_id')->nullable()->after('fees_type_id');
            $table->index('finance_category_id');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('fees_class_types')) {
            return;
        }

        if (Schema::hasColumn('fees_class_types', 'finance_category_id')) {
            Schema::table('fees_class_types', function (Blueprint $table) {
                $table->dropColumn('finance_category_id');
            });
        }
    }
};
