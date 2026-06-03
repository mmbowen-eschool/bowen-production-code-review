<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studios', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('timezone', 64);
            $table->char('currency', 3);
            $table->string('phone', 40)->nullable();
            $table->string('address', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });

        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_id')->constrained('studios')->cascadeOnDelete();
            $table->string('name', 80);
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('studio_id');
            $table->unique(['studio_id', 'name']);
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_id')->constrained('studios')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('email', 191)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('password', 255);
            $table->enum('role', ['admin', 'front_desk', 'teacher']);
            $table->boolean('is_active')->default(true);
            $table->dateTime('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();

            $table->index(['studio_id', 'role']);
            $table->index(['studio_id', 'is_active']);
            $table->unique(['studio_id', 'email']);
            $table->unique(['studio_id', 'phone']);
        });

        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_id')->constrained('studios')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('display_name', 120)->nullable();
            $table->text('bio')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique('user_id');
            $table->index(['studio_id', 'is_active']);
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_id')->constrained('studios')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('phone', 40)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['studio_id', 'name']);
            $table->unique(['studio_id', 'phone']);
        });

        Schema::create('class_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_id')->constrained('studios')->cascadeOnDelete();
            $table->string('name', 120);
            $table->enum('kind', ['regular', 'private', 'both']);
            $table->unsignedSmallInteger('default_duration_minutes')->nullable();
            $table->unsignedSmallInteger('default_deduct_units')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['studio_id', 'kind']);
            $table->unique(['studio_id', 'name']);
        });

        Schema::create('package_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_id')->constrained('studios')->cascadeOnDelete();
            $table->string('name', 120);
            $table->unsignedSmallInteger('lessons_count');
            $table->unsignedSmallInteger('validity_days')->nullable();
            $table->decimal('price', 12, 2);
            $table->char('currency', 3);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['studio_id', 'is_active']);
            $table->unique(['studio_id', 'name']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_id')->constrained('studios')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3);
            $table->enum('method', ['cash', 'bank_transfer', 'card', 'other']);
            $table->enum('status', ['pending', 'paid', 'void', 'refunded']);
            $table->dateTime('paid_at')->nullable();
            $table->string('reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['studio_id', 'student_id', 'paid_at']);
            $table->index(['studio_id', 'status', 'created_at']);
            $table->unique(['studio_id', 'reference']);
        });

        Schema::create('student_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_id')->constrained('studios')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('package_type_id')->constrained('package_types')->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->dateTime('purchased_at');
            $table->dateTime('expires_at')->nullable();
            $table->unsignedInteger('total_units');
            $table->unsignedInteger('remaining_units');
            $table->enum('status', ['active', 'expired', 'void'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['studio_id', 'student_id', 'status', 'expires_at']);
            $table->index(['studio_id', 'expires_at']);
        });

        Schema::create('regular_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_id')->constrained('studios')->cascadeOnDelete();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->foreignId('class_type_id')->constrained('class_types')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['studio_id', 'room_id', 'day_of_week', 'start_time', 'end_time'], 'regular_classes_room_time');
            $table->index(['studio_id', 'teacher_id', 'day_of_week', 'start_time', 'end_time'], 'regular_classes_teacher_time');
            $table->index(['studio_id', 'starts_on', 'ends_on', 'is_active'], 'regular_classes_effective');
        });

        Schema::create('private_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_id')->constrained('studios')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->foreignId('class_type_id')->nullable()->constrained('class_types')->nullOnDelete();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->enum('status', ['pending', 'confirmed', 'rejected', 'cancelled', 'completed'])->default('pending');
            $table->foreignId('requested_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('rejection_reason', 255)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['studio_id', 'student_id', 'start_at'], 'private_bookings_student_time');
            $table->index(['studio_id', 'teacher_id', 'start_at', 'end_at'], 'private_bookings_teacher_time');
            $table->index(['studio_id', 'room_id', 'start_at', 'end_at'], 'private_bookings_room_time');
            $table->index(['studio_id', 'status', 'start_at'], 'private_bookings_status_time');
            $table->unique(['studio_id', 'room_id', 'start_at'], 'private_bookings_room_start_unique');
            $table->unique(['studio_id', 'teacher_id', 'start_at'], 'private_bookings_teacher_start_unique');
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_id')->constrained('studios')->cascadeOnDelete();
            $table->foreignId('private_booking_id')->constrained('private_bookings')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->enum('status', ['present', 'absent', 'no_show']);
            $table->dateTime('checked_in_at');
            $table->foreignId('checked_in_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('voided_at')->nullable();
            $table->foreignId('voided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('private_booking_id');
            $table->index(['studio_id', 'status', 'checked_in_at'], 'attendance_studio_status_time');
            $table->index(['studio_id', 'student_id', 'checked_in_at'], 'attendance_student_time');
        });

        Schema::create('package_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_id')->constrained('studios')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('student_package_id')->constrained('student_packages')->cascadeOnDelete();
            $table->enum('type', ['purchase', 'deduction', 'void_deduction', 'adjustment']);
            $table->integer('units_delta');
            $table->unsignedInteger('balance_after');
            $table->dateTime('occurred_at');
            $table->foreignId('attendance_record_id')->nullable()->constrained('attendance_records')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['studio_id', 'student_id', 'occurred_at'], 'pkg_tx_student_time');
            $table->index(['studio_id', 'student_package_id', 'occurred_at'], 'pkg_tx_package_time');
            $table->index(['studio_id', 'type', 'occurred_at'], 'pkg_tx_type_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_transactions');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('private_bookings');
        Schema::dropIfExists('regular_classes');
        Schema::dropIfExists('student_packages');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('package_types');
        Schema::dropIfExists('class_types');
        Schema::dropIfExists('students');
        Schema::dropIfExists('teachers');
        Schema::dropIfExists('users');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('studios');
    }
};

