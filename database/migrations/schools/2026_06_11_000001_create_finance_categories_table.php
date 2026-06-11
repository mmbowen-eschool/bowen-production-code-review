<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('finance_categories')) {
            return;
        }

        Schema::create('finance_categories', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->comment('income or expense');
            $table->string('category_code', 100);
            $table->string('name', 255);
            $table->string('local_name', 255)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['category_code'], 'finance_categories_code_unique');
            $table->index(['type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_categories');
    }
};
